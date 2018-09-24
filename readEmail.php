<?php	

	require("Imap.php");
        $email_address = "your_email_address";
	$email_password = "your_email_password";
	$email_host = "email_hostname"; //imap.gmail.com | outlook.office365.com
	$port = 993;
	$protocol = "imap";
	$ssl = "ssl";

	// Create an Imap object and pass the email credentials as params
	$imap = new Imap($email_address,$email_password,$email_host,$port,$protocol,$ssl);
	
	// Create a connection with email
	$inbox  = $imap->ImapConnection();
	
	// Search email Inbox 
	$emails = $imap->Search('UNSEEN');
		if($emails){
			rsort($emails);
			echo '<table border="1" style="border:1px solid black;padding:8px;">';
			echo '<tr>';
			echo '<td style="padding:8px;">Status</td>';
			echo '<td style="padding:8px;">From Name</td>';
			echo '<td style="padding:8px;">From Address</td>';
			echo '<td style="padding:8px;">CC Addresses</td>';
			echo '<td style="padding:8px;">Subject</td>';
			echo '<td style="padding:8px;">Date</td>';
			echo '<td style="padding:8px;">Email NUmber</td>';
			echo '<td style="padding:8px;">Body</td>';
			echo '<td style="padding:8px;">Attachments filenames</td>';
			echo '</tr>';
			//loop all the emails
			foreach($emails as $email_number){
				
				$imap->setEmailNumber($email_number);
				$imap->getHeader();
				$seen = $imap->getSeen();
				$recent = $imap->getRecent();
				$from_name = $imap->getFromName();
				$from_address = $imap->getFromAddress();
				$cc = $imap->getCc();
				$cc = substr($cc,0,-1);
				$subject = $imap->getSubject();
				$data = $imap->getData();
				$msgNo = $imap->getMsgNo();
				
				$imap->fetch();	
				$bodyPlain = $imap->getPlain();
				$bodyHTML = $imap->getHtml();
				
				if(!empty($bodyHTML )){
					$body = $bodyHTML;
				}
				else{
					$body = $bodyPlain;
				}
				if(strcmp($seen,'U') == 0 || strcmp($recent,'N') == 0){
					$read = "Not Read";
				}
				else{
					$read = "Read";
				}

				$imap->readAttachment("dir_name");					
				$file = $imap->getFileNames();
				echo '<tr>';
				echo '<td style="padding:8px;">'.$read.'</td>';
				echo '<td style="padding:8px;">'.$from_name.'</td>';
				echo '<td style="padding:8px;">'.$from_address.'</td>';
				echo '<td style="padding:8px;">'.$cc.'</td>';
				echo '<td style="padding:8px;">'.$subject.'</td>';
				echo '<td style="padding:8px;">'.$data.'</td>';
				echo '<td style="padding:8px;">'.$msgNo.'</td>';
				echo '<td style="padding:8px;">'.$body.'</td>';
				echo '<td style="padding:8px;">'.$file.'</td>';
				echo '</tr>';
			}
			echo "</table>";
		}
		$imap->Close();
	
	

	
?>
