<?php

final class tragofone_selfcare_conflict_exception extends RuntimeException {}

final class tragofone_selfcare_repository {
	public function __construct(private readonly database $database, private readonly tragofone_crypto $crypto) {}

	public function global_config(): array {
		return $this->row('select * from v_tragofone_global_config order by update_date desc nulls last limit 1') ?? [];
	}

	public function subject(string $subject_uuid): ?array {
		if (!self::uuid_valid($subject_uuid)) { return null; }
		$sql = "select s.*, e.*, d.domain_name, m.sync_status,t.selfcare_policy as domain_selfcare_policy,p.selfcare_policy as user_selfcare_policy from v_tragofone_selfcare_subjects s "
			."join v_extensions e on e.extension_uuid=s.extension_uuid and e.domain_uuid=s.domain_uuid "
			."join v_domains d on d.domain_uuid=s.domain_uuid "
			."join v_tragofone_tenants t on t.domain_uuid=s.domain_uuid and t.enabled=true "
			."left join v_tragofone_extension_policies p on p.extension_uuid=s.extension_uuid and p.domain_uuid=s.domain_uuid "
			."join v_tragofone_extension_mappings m on m.extension_uuid=s.extension_uuid and m.domain_uuid=s.domain_uuid and m.deleted_at is null "
			."where s.subject_uuid=:subject_uuid and s.active=true and e.enabled=true and m.sync_status='synchronized'";
		return $this->row($sql, compact('subject_uuid'));
	}

	public function consume_assertion(string $subject_uuid, string $tragofone_time, string $tragofone_hash): bool {
		$assertion_hash = hash('sha256', $subject_uuid."\0".$tragofone_time."\0".$tragofone_hash);
		$sql = "insert into v_tragofone_selfcare_assertions (assertion_hash,subject_uuid,expires_at,consumed_at,insert_date) "
			."values (:hash,:subject_uuid,now() + interval '2 minutes',now(),now()) on conflict (assertion_hash) do nothing returning assertion_hash";
		$result = $this->database->execute($sql, ['hash'=>$assertion_hash,'subject_uuid'=>$subject_uuid], 'row');
		return is_array($result) && !empty($result['assertion_hash']);
	}

	public function rate_limit(string $remote_address, string $subject_hint = ''): bool {
		$bucket = $this->crypto->fingerprint('selfcare-rate-limit', $remote_address."\0".$subject_hint);
		$sql = "insert into v_tragofone_selfcare_rate_limits (bucket_hash,window_start,attempts,blocked_until,update_date) "
			."values (:bucket,now(),1,null,now()) on conflict (bucket_hash) do update set "
			."attempts=case when v_tragofone_selfcare_rate_limits.window_start < now()-interval '1 minute' then 1 else v_tragofone_selfcare_rate_limits.attempts+1 end, "
			."window_start=case when v_tragofone_selfcare_rate_limits.window_start < now()-interval '1 minute' then now() else v_tragofone_selfcare_rate_limits.window_start end, "
			."blocked_until=case when v_tragofone_selfcare_rate_limits.blocked_until > now() then v_tragofone_selfcare_rate_limits.blocked_until "
			."when v_tragofone_selfcare_rate_limits.window_start >= now()-interval '1 minute' and v_tragofone_selfcare_rate_limits.attempts >= 9 then now()+interval '5 minutes' else null end, update_date=now() "
			."returning attempts,blocked_until";
		$row = $this->database->execute($sql, ['bucket'=>$bucket], 'row');
		return is_array($row) && empty($row['blocked_until']);
	}

	public function create_session(array $subject, array $theme, int $idle_seconds, int $absolute_seconds, string $remote_address, string $user_agent): array {
		$idle_seconds = min(3600, max(300, $idle_seconds)); $absolute_seconds = min(86400, max($idle_seconds, $absolute_seconds));
		$session_uuid = tragofone_scanner::uuid(); $secret = self::random_token(); $csrf = hash_hmac('sha256', 'csrf', $secret);
		$record = [
			'session_uuid'=>$session_uuid, 'subject_uuid'=>$subject['subject_uuid'], 'token_hash'=>hash('sha256', $secret),
			'csrf_hash'=>hash('sha256', $csrf), 'theme_payload'=>json_encode($theme, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
			'ip_hash'=>$this->crypto->fingerprint('selfcare-session-ip', $remote_address),
			'user_agent_hash'=>$this->crypto->fingerprint('selfcare-session-agent', $user_agent),
			'created_at'=>gmdate('c'), 'last_seen_at'=>gmdate('c'), 'idle_expires_at'=>gmdate('c', time()+$idle_seconds),
			'absolute_expires_at'=>gmdate('c', time()+$absolute_seconds),
		];
		$this->upsert('v_tragofone_selfcare_sessions', 'session_uuid', $record);
		return ['cookie'=>$session_uuid.'.'.$secret, 'csrf'=>$csrf];
	}

	public function authenticate(?string $cookie, string $remote_address, string $user_agent): ?array {
		if (!is_string($cookie) || !preg_match('/^([0-9a-f-]{36})\.([A-Za-z0-9_-]{40,})$/i', $cookie, $matches) || !self::uuid_valid($matches[1])) { return null; }
		$sql = "select ss.*,s.domain_uuid,s.extension_uuid,s.active,e.*,d.domain_name,m.sync_status,t.selfcare_policy as domain_selfcare_policy,p.selfcare_policy as user_selfcare_policy from v_tragofone_selfcare_sessions ss "
			."join v_tragofone_selfcare_subjects s on s.subject_uuid=ss.subject_uuid "
			."join v_extensions e on e.extension_uuid=s.extension_uuid and e.domain_uuid=s.domain_uuid "
			."join v_domains d on d.domain_uuid=s.domain_uuid "
			."join v_tragofone_tenants t on t.domain_uuid=s.domain_uuid and t.enabled=true "
			."left join v_tragofone_extension_policies p on p.extension_uuid=s.extension_uuid and p.domain_uuid=s.domain_uuid "
			."join v_tragofone_extension_mappings m on m.extension_uuid=s.extension_uuid and m.domain_uuid=s.domain_uuid and m.deleted_at is null "
			."where ss.session_uuid=:session_uuid and ss.revoked_at is null and ss.idle_expires_at>now() and ss.absolute_expires_at>now() "
			."and s.active=true and e.enabled=true and m.sync_status='synchronized'";
		$row = $this->row($sql, ['session_uuid'=>$matches[1]]);
		if ($row === null || !hash_equals((string) $row['token_hash'], hash('sha256', $matches[2]))) { return null; }
		$config = $this->global_config();
		if (!tragofone_selfcare_policy::enabled(tragofone_selfcare_policy::global($config), $row['domain_selfcare_policy'] ?? 'inherit', $row['user_selfcare_policy'] ?? 'inherit')) { return null; }
		if (!hash_equals((string) $row['ip_hash'], $this->crypto->fingerprint('selfcare-session-ip', $remote_address))
			|| !hash_equals((string) $row['user_agent_hash'], $this->crypto->fingerprint('selfcare-session-agent', $user_agent))) { return null; }
		$idle = min(3600, max(300, (int) ($config['selfcare_session_idle_seconds'] ?? 900)));
		$this->execute('update v_tragofone_selfcare_sessions set last_seen_at=now(),idle_expires_at=least(now() + (:idle || \' seconds\')::interval,absolute_expires_at) where session_uuid=:session_uuid', ['idle'=>$idle,'session_uuid'=>$row['session_uuid']]);
		$row['csrf'] = hash_hmac('sha256', 'csrf', $matches[2]);
		$row['theme'] = json_decode((string) $row['theme_payload'], true) ?: [];
		return $row;
	}

	public function verify_csrf(array $session, mixed $token): bool {
		return is_string($token) && $token !== '' && hash_equals((string) $session['csrf_hash'], hash('sha256', $token));
	}

	public function revoke_session(string $session_uuid): void {
		if (self::uuid_valid($session_uuid)) { $this->execute('update v_tragofone_selfcare_sessions set revoked_at=now() where session_uuid=:uuid', ['uuid'=>$session_uuid]); }
	}

	public function summary(array $session): array {
		$extension_uuid = $session['extension_uuid']; $domain_uuid = $session['domain_uuid'];
		$dids = $this->select("select did_number from v_tragofone_did_mappings where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid and enabled=true order by did_number", compact('domain_uuid','extension_uuid'));
		$mailbox = $this->mailbox($session);
		return [
			'display_name'=>trim((string) ($session['effective_caller_id_name'] ?? $session['description'] ?? '')),
			'extension'=>(string) $session['extension'], 'caller_id'=>(string) ($session['effective_caller_id_number'] ?? ''),
			'dids'=>array_values(array_filter(array_map(static fn($row)=>(string)$row['did_number'], $dids))),
			'mailbox'=>$mailbox['voicemail_id'] ?? null, 'call_state'=>$this->call_state($session),
		];
	}

	public function call_state(array $session): array {
		$row = $this->row('select update_date,do_not_disturb,follow_me_enabled,forward_all_enabled,forward_all_destination,forward_busy_enabled,forward_busy_destination,forward_no_answer_enabled,forward_no_answer_destination,forward_user_not_registered_enabled,forward_user_not_registered_destination from v_extensions where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid', ['domain_uuid'=>$session['domain_uuid'],'extension_uuid'=>$session['extension_uuid']]);
		if ($row === null) { throw new RuntimeException('Extension call handling is unavailable.'); }
		return $row;
	}

	public function update_call_state(array $session, array $input): void {
		$current = $this->call_state($session); $modes = [
			'all'=>['forward_all_enabled','forward_all_destination'], 'busy'=>['forward_busy_enabled','forward_busy_destination'],
			'no_answer'=>['forward_no_answer_enabled','forward_no_answer_destination'],
			'not_registered'=>['forward_user_not_registered_enabled','forward_user_not_registered_destination'],
		];
		$record = ['do_not_disturb'=>self::truth($input['do_not_disturb'] ?? false), 'follow_me_enabled'=>$current['follow_me_enabled'] ?? false];
		$config = $this->global_config();
		foreach ($modes as $mode => [$enabled_field,$destination_field]) {
			$enabled = self::truth($input[$enabled_field] ?? false); $destination = trim((string) ($input[$destination_field] ?? ''));
			if ($enabled) { $destination = $this->validate_forward_destination($session, $destination, $config); }
			else { $destination = ''; }
			$record[$enabled_field] = $enabled; $record[$destination_field] = $destination;
		}
		if ($record['do_not_disturb']) { $record['forward_all_enabled'] = false; $record['forward_all_destination'] = ''; $record['follow_me_enabled'] = false; }
		elseif ($record['forward_all_enabled']) { $record['do_not_disturb'] = false; $record['follow_me_enabled'] = false; }
		$expected = (string) ($input['expected_update_date'] ?? '');
		$sets=[]; $params=['domain_uuid'=>$session['domain_uuid'],'extension_uuid'=>$session['extension_uuid'],'expected'=>$expected];
		foreach ($record as $field=>$value) { $sets[]=$field.'=:'.$field; $params[$field]=is_bool($value)?($value?'true':'false'):$value; }
		$sql = 'update v_extensions set '.implode(',', $sets).',update_date=now() where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid and coalesce(update_date::text,\'\')=:expected returning extension_uuid';
		$result = $this->database->execute($sql, $params, 'row');
		if (!is_array($result)) { throw new tragofone_selfcare_conflict_exception('Call settings changed elsewhere. Refresh and try again.'); }
		$this->invalidate_extension($session, $record);
		$this->audit($session, 'selfcare.call_handling.update', 'Call handling settings updated.');
	}

	public function mailbox(array $session): ?array {
		$candidates = array_values(array_unique(array_filter([(string) $session['extension'], (string) ($session['number_alias'] ?? '')], static fn($value)=>preg_match('/^\d+$/',$value))));
		if ($candidates === []) { return null; }
		$params=['domain_uuid'=>$session['domain_uuid']]; $holders=[];
		foreach ($candidates as $index=>$candidate) { $holders[]=':mailbox'.$index; $params['mailbox'.$index]=$candidate; }
		$rows=$this->select('select * from v_voicemails where domain_uuid=:domain_uuid and voicemail_enabled=true and voicemail_id in ('.implode(',',$holders).')', $params);
		foreach ($candidates as $candidate) { foreach ($rows as $row) { if ((string)$row['voicemail_id']===$candidate) { return $row; } } }
		return null;
	}

	public function voicemail_messages(array $session): array {
		$mailbox=$this->mailbox($session); if ($mailbox===null) { return []; }
		return $this->select('select voicemail_message_uuid,voicemail_uuid,created_epoch,read_epoch,caller_id_name,caller_id_number,message_length,message_status,message_priority,message_transcription from v_voicemail_messages where domain_uuid=:domain_uuid and voicemail_uuid=:voicemail_uuid order by created_epoch desc', ['domain_uuid'=>$session['domain_uuid'],'voicemail_uuid'=>$mailbox['voicemail_uuid']]);
	}

	public function voicemail_message_handle(array $session, string $message_uuid): string {
		if (!self::uuid_valid($message_uuid) || !self::uuid_valid((string) ($session['session_uuid'] ?? ''))) { return ''; }
		return $this->crypto->fingerprint('selfcare-voicemail-handle:'.$session['session_uuid'], $message_uuid);
	}

	public function voicemail_message_from_handle(array $session, string $handle): ?array {
		if (!preg_match('/^[0-9a-f]{64}$/', $handle)) { return null; }
		foreach ($this->voicemail_messages($session) as $message) {
			if (hash_equals($this->voicemail_message_handle($session, (string) $message['voicemail_message_uuid']), $handle)) {
				return $this->voicemail_message($session, (string) $message['voicemail_message_uuid']);
			}
		}
		return null;
	}

	public function voicemail_message(array $session, string $message_uuid): ?array {
		if (!self::uuid_valid($message_uuid)) { return null; } $mailbox=$this->mailbox($session); if ($mailbox===null) { return null; }
		$row=$this->row('select m.*,v.voicemail_id,d.domain_name from v_voicemail_messages m join v_voicemails v on v.voicemail_uuid=m.voicemail_uuid and v.domain_uuid=m.domain_uuid join v_domains d on d.domain_uuid=m.domain_uuid where m.domain_uuid=:domain_uuid and m.voicemail_uuid=:voicemail_uuid and m.voicemail_message_uuid=:message_uuid', ['domain_uuid'=>$session['domain_uuid'],'voicemail_uuid'=>$mailbox['voicemail_uuid'],'message_uuid'=>$message_uuid]);
		return $row;
	}

	public function stream_voicemail(array $session, string $message_uuid, bool $download): never {
		$message=$this->voicemail_message($session,$message_uuid); if($message===null){http_response_code(404);exit;}
		$voicemail=$this->voicemail_service($message); $voicemail->type=$download?'bin':null; $voicemail->message_download((string)$message['domain_name']); exit;
	}

	public function set_message_read(array $session, string $message_uuid, bool $read): void {
		$message=$this->voicemail_message($session,$message_uuid); if($message===null){throw new RuntimeException('Voicemail message was not found.');}
		$this->execute('update v_voicemail_messages set message_status=:status,read_epoch=:read_epoch,update_date=now() where domain_uuid=:domain_uuid and voicemail_uuid=:voicemail_uuid and voicemail_message_uuid=:message_uuid', ['status'=>$read?'saved':null,'read_epoch'=>$read?time():null,'domain_uuid'=>$session['domain_uuid'],'voicemail_uuid'=>$message['voicemail_uuid'],'message_uuid'=>$message_uuid]);
		$this->voicemail_service($message)->message_waiting(); $this->audit($session,'selfcare.voicemail.read',$read?'Voicemail marked read.':'Voicemail marked unread.');
	}

	public function delete_message(array $session, string $message_uuid): void {
		$message=$this->voicemail_message($session,$message_uuid); if($message===null){throw new RuntimeException('Voicemail message was not found.');}
		if(!$this->voicemail_service($message)->message_delete()){throw new RuntimeException('Unable to delete voicemail message.');}
		$this->audit($session,'selfcare.voicemail.delete','Voicemail message deleted.');
	}

	public function update_voicemail_settings(array $session, string $email, string $pin): void {
		$mailbox=$this->mailbox($session); if($mailbox===null){throw new RuntimeException('Voicemail mailbox is unavailable.');}
		$email=trim($email); if($email!=='' && filter_var($email,FILTER_VALIDATE_EMAIL)===false){throw new InvalidArgumentException('Enter one valid voicemail email address.');}
		$fields=[$email===''?'voicemail_mail_to=null':'voicemail_mail_to=:email']; $params=['domain_uuid'=>$session['domain_uuid'],'voicemail_uuid'=>$mailbox['voicemail_uuid']];
		if($email!==''){$params['email']=$email;}
		if($pin!==''){$policy=$this->voicemail_pin_policy($session);if(!preg_match('/^\d{'.$policy['min'].',20}$/',$pin)){throw new InvalidArgumentException('Voicemail PIN must contain '.$policy['min'].' to 20 digits.');}if($policy['complexity']&&(preg_match('/(\d)\1{2}/',$pin)||preg_match('/(012|123|234|345|456|567|678|789|987|876|765|654|543|432|321|210)/',$pin))){throw new InvalidArgumentException('Voicemail PIN cannot contain three repeated or sequential digits.');}$fields[]='voicemail_password=:pin';$params['pin']=$pin;}
		$this->execute('update v_voicemails set '.implode(',',$fields).',update_date=now() where domain_uuid=:domain_uuid and voicemail_uuid=:voicemail_uuid',$params);
		$this->audit($session,'selfcare.voicemail.settings','Voicemail notification settings updated.');
	}

	public function voicemail_pin_policy(array $session): array {
		$settings=new settings(['database'=>$this->database,'domain_uuid'=>$session['domain_uuid']]);$complexity=tragofone_normalizer::boolean($settings->get('voicemail','password_complexity',false));$minimum=$complexity?(int)$settings->get('voicemail','password_min_length',4):4;return ['min'=>min(20,max(4,$minimum)),'complexity'=>$complexity];
	}

	public function logo(): ?array {
		$config=$this->global_config(); if(empty($config['selfcare_brand_logo_base64'])||empty($config['selfcare_brand_logo_mime'])){return null;}
		$data=base64_decode((string)$config['selfcare_brand_logo_base64'],true); return $data===false?null:['mime'=>$config['selfcare_brand_logo_mime'],'data'=>$data,'version'=>(int)($config['selfcare_brand_version']??1)];
	}

	private function validate_forward_destination(array $session,string $destination,array $config): string {
		$normalized=preg_replace('/[^+0-9]/','',$destination)??''; if(!preg_match('/^\+?\d{2,32}$/',$normalized)){throw new InvalidArgumentException('Forwarding destination must contain 2 to 32 digits.');}
		$stored=ltrim($normalized,'+'); if($stored===(string)$session['extension']||$stored===(string)($session['number_alias']??'')){throw new InvalidArgumentException('An extension cannot forward to itself.');}
		$internal=$this->row('select extension_uuid,extension,forward_all_enabled,forward_all_destination from v_extensions where domain_uuid=:domain_uuid and enabled=true and (extension=:destination or number_alias=:destination) limit 1',['domain_uuid'=>$session['domain_uuid'],'destination'=>$stored]);
		if($internal!==null){$this->assert_no_forward_loop($session,(string)$internal['extension_uuid']);return $stored;}
		if(!tragofone_normalizer::boolean($config['selfcare_external_forwarding']??false)){throw new InvalidArgumentException('External forwarding is disabled.');}
		$prefixes=array_values(array_filter(array_map('trim',explode(',',(string)($config['selfcare_external_prefixes']??''))))); if($prefixes===[]){throw new InvalidArgumentException('External forwarding has no allowed prefixes.');}
		foreach($prefixes as $prefix){$clean=preg_replace('/[^+0-9]/','',$prefix)??'';if($clean!==''&&str_starts_with($normalized,$clean)){return $stored;}}
		throw new InvalidArgumentException('The external destination is outside the allowed prefixes.');
	}

	private function assert_no_forward_loop(array $session,string $start_uuid): void {
		$origin=(string)$session['extension_uuid'];$seen=[];$queue=[$start_uuid];$visited=0;
		while($queue!==[]){$current=array_shift($queue);if($current===$origin){throw new InvalidArgumentException('Forwarding would create a loop.');}if(isset($seen[$current])){continue;}$seen[$current]=true;
			if(++$visited>100){throw new InvalidArgumentException('Forwarding chain is too complex.');}
			$row=$this->row('select forward_all_enabled,forward_all_destination,forward_busy_enabled,forward_busy_destination,forward_no_answer_enabled,forward_no_answer_destination,forward_user_not_registered_enabled,forward_user_not_registered_destination from v_extensions where domain_uuid=:domain_uuid and extension_uuid=:uuid and enabled=true',['domain_uuid'=>$session['domain_uuid'],'uuid'=>$current]);
			if($row===null){continue;}
			foreach([['forward_all_enabled','forward_all_destination'],['forward_busy_enabled','forward_busy_destination'],['forward_no_answer_enabled','forward_no_answer_destination'],['forward_user_not_registered_enabled','forward_user_not_registered_destination']] as [$enabled,$destination]){
				if(!self::truth($row[$enabled]??false)||empty($row[$destination])){continue;}$next=$this->row('select extension_uuid from v_extensions where domain_uuid=:domain_uuid and enabled=true and (extension=:destination or number_alias=:destination) limit 1',['domain_uuid'=>$session['domain_uuid'],'destination'=>$row[$destination]]);if($next!==null){$queue[]=(string)$next['extension_uuid'];}
			}
		}
	}

	private function invalidate_extension(array $session,array $state): void {
		if(class_exists('cache')){$cache=new cache;$cache->delete('directory:'.$session['extension'].'@'.$session['domain_name']);if(!empty($session['number_alias'])){$cache->delete('directory:'.$session['number_alias'].'@'.$session['domain_name']);}}
		$root=dirname(__DIR__,4);$notify_file=$root.'/app/call_forward/resources/classes/feature_event_notify.php';
		if(is_file($notify_file)&&class_exists('event_socket')){require_once $notify_file;if(class_exists('feature_event_notify')){$notify=new feature_event_notify;$notify->domain_name=$session['domain_name'];$notify->extension=$session['extension'];$notify->do_not_disturb=$state['do_not_disturb']?'true':'false';$notify->forward_all_enabled=$state['forward_all_enabled'];$notify->forward_all_destination=$state['forward_all_destination']?:'0';$notify->forward_busy_enabled=$state['forward_busy_enabled'];$notify->forward_busy_destination=$state['forward_busy_destination']?:'0';$notify->forward_no_answer_enabled=$state['forward_no_answer_enabled'];$notify->forward_no_answer_destination=$state['forward_no_answer_destination']?:'0';$notify->ring_count=5;$notify->send_notify();}}
	}

	private function voicemail_service(array $message): object {
		$file=dirname(__DIR__,4).'/app/voicemails/resources/classes/voicemail.php';if(!is_file($file)){throw new RuntimeException('FusionPBX voicemail service is unavailable.');}require_once $file;
		$settings=new settings(['database'=>$this->database,'domain_uuid'=>$message['domain_uuid']]);$service=new voicemail(['database'=>$this->database,'settings'=>$settings,'domain_uuid'=>$message['domain_uuid'],'domain_name'=>$message['domain_name']]);$service->voicemail_id=$message['voicemail_id'];$service->voicemail_uuid=$message['voicemail_uuid'];$service->voicemail_message_uuid=$message['voicemail_message_uuid'];return $service;
	}

	private function audit(array $session,string $action,string $summary): void {
		$this->execute('insert into v_tragofone_audit (audit_uuid,domain_uuid,action,entity_type,entity_uuid,summary,insert_date) values (:uuid,:domain_uuid,:action,\'extension\',:entity_uuid,:summary,now())',['uuid'=>tragofone_scanner::uuid(),'domain_uuid'=>$session['domain_uuid'],'action'=>$action,'entity_uuid'=>$session['extension_uuid'],'summary'=>$summary]);
	}

	private function select(string $sql,array $parameters=[]):array{return $this->database->select($sql,$parameters,'all')?:[];}
	private function row(string $sql,array $parameters=[]):?array{$row=$this->database->select($sql,$parameters,'row');if(is_array($row)&&$row!==[]){return $row;}$rows=$this->database->select($sql,$parameters,'all')?:[];return $rows[0]??null;}
	private function execute(string $sql,array $parameters=[]):void{if($this->database->execute($sql,$parameters)===false){throw new RuntimeException('FusionPBX database write failed.');}}
	private function upsert(string $table,string $primary,array $record):void{$columns=array_keys($record);$updates=array_values(array_diff($columns,[$primary,'created_at']));$sql='insert into '.$table.' ('.implode(',',$columns).') values (:'.implode(',:',$columns).') on conflict ('.$primary.') do update set '.implode(',',array_map(static fn($field)=>$field.'=excluded.'.$field,$updates));$this->execute($sql,$record);}
	private static function truth(mixed $value):bool{return tragofone_normalizer::boolean($value)||in_array($value,['t','on'],true);}
	private static function random_token():string{return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');}
	private static function uuid_valid(string $value):bool{return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$value);}
}
