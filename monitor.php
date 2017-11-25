<?php
//Author: Thiago Tognoli Lopes - thitognoli-btc@yahoo.com.br
//License AGPL
//Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
require 'vendor/autoload.php';

$config = include('config.php');
$painelId = $config['painelId'];
$config = $config[$painelId];

$url = "http://".$painelId.".ethosdistro.com/?json=yes";

$minHash = $config['hash_min'];
$minGpus = $config['gpus_min'];
$minRigs = $config['rigs_min'];

//periodicidade de envio de relatório normal
$reportCurrentStatusInterval = 60*60*$config['report_time'];


$lastHashReport = 0;

$reportHash = true;
$reportGpus = true;
$reportRigs = true;

$lastReport = 0;
while (true) {

	$jsonStatus = file_get_contents($url);
	$status = json_decode($jsonStatus,true);
	if (!is_array($status)) {
		echo "Problema no monitor";
		sleep(30);
		continue;
	}

	$reportStatus = ($lastReport + $reportCurrentStatusInterval < time());
	$reportTitle = "";
	if ($reportStatus) {
		$lastReport = time();
		$reportTitle = "Relatório de Mineração - ".$lastReport;
	}
	$reports = array();

	
	if (isset($status["total_hash"])) {
		$hash = $status["total_hash"];
		$reports[] = "Hash atual: ".$hash;
		if($hash < $minHash && $reportHash) {
			$reportTitle = "Problemas com Mineração - ".time();
			$reports[] = "Hash Mínimo não atingido.";
			$reportHash = false;
		} else if ($hash >= $minHash) {
			$reportHash = true;
		}
	} else if ($reportHash) {
		$reportTitle = "Problemas com Monitoramento da Mineração - ".time();
		$reports[] =  "Problema com monitoramento de Hash";
		$reportHash = false;
	}
	
	if (isset($status["alive_rigs"])) {
		$rigs = $status["alive_rigs"];
		$reports[] = "Rig's online: ".$rigs." de ".$minRigs;
		if($rigs < $minRigs && $reportRigs) {
			$reportTitle = "Problemas com Mineração - ".time();
			$reports[] = "Existem Rig's offline.";
			$reportRigs = false;
		} else if ($rigs >= $minRigs) {
			$reportRigs = true;
		}
	} else if ($reportRigs){
		$reportTitle = "Problemas com Monitoramento da Mineração - ".time();
		$reports[] =  "Problema com monitoramento de Rigs's";
		$reportRigs = false;
	}

	if (isset($status["alive_gpus"])) {
		$gpus = $status["alive_gpus"];
		$reports[] = "GPU's online: ".$gpus." de ".$minGpus;
		if($gpus < $minGpus && $reportGpus) {
			$reportTitle = "Problemas com Mineração - ".time();
			$reports[] = "Existem GPU's offline.";
			$reportGpus = false;
		} else if ($gpus >= $minGpus) {
			$reportGpus = true;
		}
	} else if ($reportGpus) {
		$reportTitle = "Problemas com Monitoramento da Mineração - ".time();
		$reports[] =  "Problema com monitoramento de GPU's";
		$reportGpus = false;
	}

	$message = "";
	foreach ($reports as $report) {
		$message .= $report."\n";
	}
	
	if ($reportTitle) {
		echo "=================================================================\n";
		echo $reportTitle."\n";
		echo "=================================================================\n";
		echo $message;
		
		try {
			$mail = new PHPMailer();
			$mail->isSMTP();
			//$mail->SMTPDebug = 2; // 0 = off (for production use) // 1 = client messages // 2 = client and server messages
			//Mail server
			$mail->Host = $config['email']['host']; // use // $mail->Host = gethostbyname('smtp.gmail.com'); // if your network does not support SMTP over IPv6
			$mail->Port = $config['email']['port'];
			$mail->SMTPSecure = $config['email']['secure']; //Set the encryption system to use - ssl (deprecated) or tls
			//SMTP authentication
			$mail->SMTPAuth = true;
			$mail->Username = $config['email']['username'];
			$mail->Password = $config['email']['password'];

			//Addresses
			//Set who the message is to be sent from
			$mail->setFrom($config['email']['from'], $config['email']['from_name']);
			//Set an alternative reply-to address
			$mail->addReplyTo($config['email']['reply'], $config['email']['reply_name']);
			//Set who the message is to be sent to
			foreach($config['email']['to'] as $to) {
				$mail->addAddress($to['email'], $to['name']);
			}

			//Content
			$mail->CharSet = 'UTF-8';
			$mail->isHTML(true);
			$mail->Subject = $reportTitle;
			$mail->Body    = nl2br($message);
			$mail->AltBody = $message;
			//$mail->addAttachment('images/phpmailer_mini.png');
			//$mail->AddStringAttachment($jsonStatus, 'status.json', 'utf8', 'application/json');
			if (!$mail->send()) {
				echo "Mailer Error: " . $mail->ErrorInfo;
			} else {
				echo "Message sent!";
			}
		} catch (Excpetion $e) {
			echo "Mailer Error: " . $e;
		}

	}
	
	sleep(90);
}
