<?php 

class Imap{
	// attachments directory path
	const ATTACHMENT_DIR = "attachments/";
	// email conenction
	private $email;
	private $password;
	private $hostname;
	private $port;
	private $protocol;
	private $ssl;
	
	private $inbox;
	private $emails;
	private $email_number;
	
	// email header information
	private $seen;
	private $recent;
	private $from_name;
	private $from_address;
	private $subject;
	private $data;
	private $msgNo;
	private $cc;
	
	public $bodyHTML = '';
	public $bodyPlain = '';
	public $structure;
	public $encoding = '';
	public $attachments;
	public $getAttachments = true;
	public $fileNames;
	
	
	function __construct($username,$password,$hostname,$port,$protocol,$ssl){
		$this->email = $username;
		$this->password = $password;
		$this->hostname = $hostname;
		$this->port = $port;
		$this->protocol = $protocol;
		$this->ssl = $ssl;
	}
	
	/**
	 * Connect with email inbox using imap_open
	 */
	public function ImapConnection(){
		$this->inbox = imap_open('{'.$this->hostname.':'.$this->port.'/'.$this->protocol.'/'.$this->ssl.'}INBOX',$this->email,$this->password) or die('Cannot connect to Mail: ' . imap_last_error());
		return $this->inbox;
	}
	
	/**
	 * Search inbox based on the condition
	 * @param string check imap_search() in php documentation
	 * @return array of message numbers
	 */
	public function Search($condition){
		$this->emails = imap_search($this->inbox,$condition);
		return $this->emails;
	}
	
	/**
	 * Message Number is used in imap_headerinfo() and imap_fetchstructure() as a param
	 * to identify a specific message
	 * @param integer The message number
	 */
	public function setEmailNumber($email_number){
		$this->email_number = $email_number;
		$this->bodyHTML = '';
		$this->bodyPlain = '';
		$this->encoding = '';
		$this->cc = '';
		$this->attachments = [];
	}
	
	/**
	 * Get email header information
	 */
	public function getHeader(){
		
		// Check php documentation for more information about imap_headerinfo()
		$overview = imap_headerinfo($this->inbox,$this->email_number,0);
		
		$this->seen = $overview->Unseen; 
		$this->recent = $overview->Recent;
		
		// Get email sender (Name and Email Address)
		$from = $overview->from;
		foreach ($from as $object) {
			if(isset($object->personal)){
				$this->from_name = iconv_mime_decode($object->personal,0,"UTF-8");
			}
			else{
				$this->from_name = "No Name";
			}
			if(isset($object->mailbox) && isset($object->host)){
				$this->from_address = $object->mailbox . "@" . $object->host;
			}
			else{
				$this->from_address = "noemail@domain.com";
			}
		}
		
		// Get all CC email addresses
		if(isset($overview->cc)){
			$ccc = $overview->cc;
			foreach($ccc as $object){
				if(isset($object->mailbox) && isset($object->host)){
					$this->cc .= $object->mailbox . "@" . $object->host.',';
				}
			}
		}
		else{
			$this->cc = "cc@domain.com,";
		}

		// Get email Subject
		if(isset($overview->subject)){
			$this->subject = iconv_mime_decode($overview->subject,0,"UTF-8");
		}
		else{
			$this->subject = "No Subject";
		}

		// Get Email datetime
		if(isset($overview->date)){
			$this->data = $overview->date;
		}
		else{
			$this->data = $this->setDate();
		}

		// GET message number
		if(isset($overview->Msgno)){
			$this->msgNo = $overview->Msgno;
		}
	}
	
	

	/**
	 *  Fetches all the structured information for a given message.
	 */
	public function fetch(){
		
		$structure = imap_fetchstructure($this->inbox, $this->email_number);
		$myobj = get_object_vars($structure);
		if(isset($myobj['parts'])){
			$this->recursive($structure->parts);			
		}
		else{
			$this->norecursive($structure);
		}
	}
		

	/**
	 * Loop the structure of a message recursive
	 * @param object The structure of a specific email
	 * $prefix and $index are used to specify the part of a structure
	 * Print the $structure to see how is organised
	 */
	public function recursive($structure, $prefix = '', $index = 1,$fullPrefix = true){
		
		
		foreach($structure as $part) {
			$partNumber = $prefix . $index;
			$disposition = (isset($part->disposition) ? $part->disposition : null);
			
			if($part->type == 0 && $disposition != 'ATTACHMENT') {
				if($part->subtype == 'PLAIN') {
					$this->bodyPlain .= $this->getPartRecursive($part->encoding, $partNumber);
				}
				else {
					$this->bodyHTML .= $this->getPartRecursive($part->encoding, $partNumber);
				}
			}
			else if($part->type == 2) {
				$this->recursive($part->parts, $partNumber.'.', 0,false);
				$this->attachments[] = array(
					'type' => $part->type,
					'subtype' => $part->subtype,
					'filename' => '',
					'data' => $this->recursive($part->parts, $partNumber.'.', 0,false),
					'inline' => false,
				);
			}
			else if(isset($part->parts)) {
				if($fullPrefix) {
					$this->recursive($part->parts, $prefix.$index.'.');
				}
				else {
					$this->recursive($part->parts, $prefix);
				}
			}
			else if($part->type > 2 || $disposition == 'ATTACHMENT') {
				if(isset($part->id)){
					$id = str_replace(array('<','>'), '', $part->id);
					$this->attachments[$id] = array(
						'type' => $part->type,
						'subtype' => $part->subtype,
						'filename' => $this->getFilenameFromPart($part),
						'data' => $this->getAttachments ? $this->getPartRecursive($part->encoding, $partNumber) : '',
						'inline' => true,
					);
				}
				else {
					$this->attachments[] = array(
						'type' => $part->type,
						'subtype' => $part->subtype,
						'filename' => $this->getFilenameFromPart($part),
						'data' => $this->getAttachments ? $this->getPartRecursive($part->encoding, $partNumber) : '',
						'inline' => false,
					);
				}
			}
			$index++;
		}
	}


	/**
	 * Read the message body
	 * @param object the structured information for a given message
	 */
	public function norecursive($structure){
		
		$body = imap_body($this->inbox,$this->email_number);
		$body = $this->getPart($body,$structure->encoding);
		
		if(strtoupper($structure->subtype) == 'PLAIN'){	
			$this->bodyPlain.= nl2br($body);
		} 
		else if(strtoupper($structure->subtype) == 'HTML'){	
			$this->bodyHTML.= $body;
		}
	}
	

	/**
	 * Saves the email attachments
	 * @param string directory name
	 * @return string has all attachmnets filenames separated by comma
	 */
	public function readAttachment($email_id){

		$dir = self::ATTACHMENT_DIR;
		$filename = "";
		if(isset($this->attachments)){
			
			if(!file_exists($dir.$email_id)) {
				$oldumask = umask(0);
				mkdir($dir.$email_id,0777);
				umask($oldumask);
			}
			foreach($this->attachments as $file){
							
				$uqid = uniqid();
				$file["filename"] = str_replace(" ", "_", $file["filename"]); // remove spaces from filename
				$filename .= $uqid.'___'.$file["filename"].";";
				$file_upload = $uqid.'___'.$file["filename"];
				
				// move file attachment to folder
				file_put_contents($dir.$email_id."/".$file_upload, $file['data']);
			}
		}
		$this->fileNames = $filename;
	}
	
	
	/**
	 * Decodes a part of message 
	 * @param string body of a specified message
	 * @param integer defines the type of encoding for the message
	 * @return string the decoded part
	 */
	function getPart($data,$encoding) {
		
		switch($encoding) {
			case 0: return $data; // 7BIT
			case 1: return $data; // 8BIT
			case 2: return imap_binary($data); // BINARY
			case 3: return imap_base64($data); // BASE64
			case 4: return quoted_printable_decode($data); // QUOTED_PRINTABLE
			case 5: return $data; // OTHER
		}
	}
	
	/**
	 * Decodes a part of message 
	 * @param string defines a part for a specific message
	 * @param integer defines the type of encoding for the message
	 * @return string the decoded part
	 */
	function getPartRecursive($encoding,$partNumber){
		
		$this->encoding .= $encoding." ";
		$data = imap_fetchbody($this->inbox, $this->email_number, $partNumber);
		switch($encoding) {
			case 0: return $data; // 7BIT
			case 1: return $data; // 8BIT
			case 2: return imap_binary($data); // BINARY
			case 3: return imap_base64($data); // BASE64
			case 4: return quoted_printable_decode($data); // QUOTED_PRINTABLE
			case 5: return $data; // OTHER
		}
	}
	
	/**
	 * Get the attachment filename 
	 * @param object A part of message structure
	 * @return string attchment filename
	 */
	function getFilenameFromPart($part) {
		$filename = '';
		if($part->ifdparameters) {
			foreach($part->dparameters as $object) {
				if(strtolower($object->attribute) == 'filename') {
					$filename = $object->value;
				}
			}
		}

		if(!$filename && $part->ifparameters) {
			foreach($part->parameters as $object) {
				if(strtolower($object->attribute) == 'name') {
					$filename = $object->value;
				}
			}
		}
		return $filename;
	}


	public function getSeen(){
		return $this->seen;
	}

	public function getRecent(){
		return $this->recent;
	}

	/**
	 * @return string message from name
	 */
	public function getFromName(){
		return $this->from_name;
	}

	/**
	 * @return string message from address
	 */
	public function getFromAddress(){
		return $this->from_address;
	}

	/**
	 * @return string message subject
	 */
	public function getSubject(){
		return $this->subject;
	}

	/**
	 * @return string message datetime
	 */
	public function getData(){
		return $this->data;
	}

	/**
	 * @return integer message number
	 */
	public function getMsgNo(){
		return $this->msgNo;
	}
	
	/**
	 * @return string the CC email adresses
	 */
	public function getCc(){
		return $this->cc;
	}
	
	/**
	 * @return string body of message as html
	 */
	public function getHtml(){
		return $this->bodyHTML;
	}

	/**
	 * @return string body of message as plain text
	 */

	public function getPlain(){
		return $this->bodyPlain;
	}
	
	/**
	 * @return string All attachment filenames separated with commas
	 */
	public function getAttachment(){
		return $this->attachments;
	}
	
	/**
	 * @return filenames String with all filenames separated with commas
	 */
	public function getFileNames(){
		return $this->fileNames;
	}
	
	/**
	 * @return currentDate return current datetime
	 */
	public function setDate(){
		$currentDate = date("D\, j F Y H:i:s O") . "<br>";
		return $currentDate;
	}
	
	/**
	 * Close imap connection
	 */
	public function Close(){
		imap_close($this->inbox);
	}
	
}

?>