<?php

$_function = function ($job)
{
	
		require_once('../phpmailer/class.phpmailer.php');

		$result = "sendmail";
		$json_result = json_encode($result);
		echo "Mails start to send...\n";

		$mail_address_list = array('xywcv@qq.com','kearneyjar@gmail.com');
		$mail = new PHPMailer();
		
		$mail->Host = "smtp.163.com"; // SMTP servers
		$mail->SMTPAuth = true; // turn on SMTP authentication
		$mail->Username = "xywcv"; // SMTP username
		$mail->Password = "19891012147"; // SMTP password 

		$mail->From = "xywcv@163.com";
		$mail->FromName = "Kearney";
		$mail->AddAddress("xywcv@qq.com","kk");
		$mail->AddAddress("kearneyjar@gmail.com","k2");

		$mail->Subject = "Here is the subject";
		$mail->Body = "This is the body";
		$mail->AltBody = "This is the text-only body";

		foreach($mail_address_list as $ad){
			$mail->AddAddress($ad);
		}

		if(!$mail->Send()){
			echo "Error! Mails were not sent.\n";
			echo "Mailer Error: " . $mail->ErrorInfo . '\n';
			exit;
		} 

		echo "Mails have been send.";
		return $json_result;

};
	
	$_register_name = 'sendmail';
	$_enable = true;
return array($_function, $_register_name, $_enable);
