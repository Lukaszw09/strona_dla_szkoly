<?php
// Komunikacja wewnętrzna użytkowników

function act_message_recipients() {
    need();
    global $U;
    $rows=all("SELECT id,name,email,role FROM users WHERE id!=? ORDER BY role,name",[$U['id']]);
    out($rows);
}

function act_message_send() {
    need();
    global $U;
    $recipient=(int)g('recipient_id');
    $subject=trim(g('subject',''));
    $body=trim(g('body',''));
    if(!$recipient || !one('SELECT 1 FROM users WHERE id=?',[$recipient])) err('Wybierz odbiorcę');
    if(mb_strlen($subject)<3 || mb_strlen($body)<2) err('Podaj temat i treść wiadomości');
    q("INSERT INTO messages(sender_id,recipient_id,subject,body,is_read,created_at) VALUES(?,?,?,?,0,datetime('now','localtime'))",
        [$U['id'],$recipient,$subject,$body]);
    logit('message_send',(string)$recipient);
    notify($recipient,'Nowa wiadomość: '.$subject);
    out(['ok'=>1]);
}

function act_message_inbox() {
    need(); global $U;
    out(all("SELECT m.*,u.name sender,u.role sender_role FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.recipient_id=? ORDER BY m.id DESC",[$U['id']]));
}

function act_message_sent() {
    need(); global $U;
    out(all("SELECT m.*,u.name recipient,u.role recipient_role FROM messages m JOIN users u ON u.id=m.recipient_id WHERE m.sender_id=? ORDER BY m.id DESC",[$U['id']]));
}

function act_message_read() {
    need(); global $U;
    $id=(int)g('id');
    q("UPDATE messages SET is_read=1 WHERE id=? AND recipient_id=?",[$id,$U['id']]);
    out(['ok'=>1]);
}

function act_privacy_consents() {
    need(); global $U;
    out(all("SELECT * FROM privacy_consents WHERE user_id=? ORDER BY id DESC",[$U['id']]));
}

function act_privacy_consent_save() {
    need(); global $U;
    $purpose=trim(g('purpose',''));
    $text=trim(g('consent_text',''));
    if($purpose==='') err('Podaj zakres zgody');
    q("INSERT INTO privacy_consents(user_id,purpose,consent,consent_text,created_at) VALUES(?,?,?,?,datetime('now','localtime'))",
        [$U['id'],$purpose,g('consent')?1:0,$text]);
    logit('privacy_consent', $purpose);
    out(['ok'=>1]);
}

function act_privacy_export() {
    need(); global $U;
    $profile=['id'=>$U['id'],'name'=>$U['name'],'email'=>$U['email'],'role'=>$U['role']];
    $consents=all("SELECT purpose,consent,consent_text,created_at FROM privacy_consents WHERE user_id=?",[$U['id']]);
    $notifications=all("SELECT text,created FROM notifications WHERE user_id=?",[$U['id']]);
    $sent=all("SELECT subject,body,created_at FROM messages WHERE sender_id=?",[$U['id']]);
    $received=all("SELECT subject,body,created_at FROM messages WHERE recipient_id=?",[$U['id']]);
    $studentData=[];
    if($U['role']==='uczen') {
        $student=one("SELECT * FROM students WHERE user_id=?",[$U['id']]);
        if($student) $studentData=[$student];
    } elseif($U['role']==='rodzic') {
        $studentData=all("SELECT s.* FROM students s JOIN parent_links l ON l.student_id=s.id WHERE l.parent_id=? AND l.restricted=0",[$U['id']]);
    }
    logit('privacy_export');
    out(['profile'=>$profile,'consents'=>$consents,'notifications'=>$notifications,'messages'=>['sent'=>$sent,'received'=>$received],'related_students'=>$studentData]);
}
