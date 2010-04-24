<?php
/**
 * Simple file manager class
 *
 * @version    1.0
 * @author     Sam Collett
 * @license    http://github.com/SamWM/sqlite-pdo/blob/master/LICENSE
 */
class WMFile {
	
	private $pathtowmfile = "wmfile.sqlite";
	public $message;
	
	// the directory of the script including this
	private $callingdir;
	private $uploadpath;
	private $virtualpath;
	
	// remote user is empty if not logged in
	private $remoteuser = "";
	
	function __construct() {
		$this->callingdir = dirname($_SERVER["SCRIPT_FILENAME"]);
		$this->uploadpath = $this->callingdir."/files/";
		$this->virtualpath = str_replace($_SERVER["DOCUMENT_ROOT"], "", $this->uploadpath);
		// get user from server variable
		if (isset($_SERVER["REDIRECT_REMOTE_USER"])) $this->remoteuser = $_SERVER["REDIRECT_REMOTE_USER"];
		elseif (isset($_SERVER["REMOTE_USER"]))	$this->remoteuser = $_SERVER["REMOTE_USER"];
		// upload file
		if($_SERVER["REQUEST_METHOD"] == "POST") {
			$title = $_POST["title"];
			$action = $_POST["action"];
			if(is_string($title) && is_array($_FILES) && $action == 'upload_document') {
				$this->message = $this->uploadFile($title, $_FILES);
			}
			if($action == "delete_document") {
				$this->message = $this->deleteFile($_POST["document_id"]);
			}
		}
	}
	
	
	public function showFiles() {
		$html = "No files found";
		
		// PDO http://uk.php.net/manual/en/class.pdo.php
		
		$wmfiledb = new PDO('sqlite:'.$this->pathtowmfile);
		
		$starttable = "<table>
	<tr>
		<th>id</th>
		<th>name</th>
		<th>size</th>
		<th>type</th>
		<th>title</th>
		<th>updated</th>
		<th>delete</th>
	</tr>";
		$endtable = "</table>";
		$tablecontents = "";
		$statement = $wmfiledb->query("SELECT * FROM downloads");
		if(!$statement) {
			$html = "Table 'downloads' does not exist in database";
		}
		else {
			$record = $statement->fetchAll();
			if(sizeof($record) != 0) {
				foreach ($record as $row) {
					$tablecontents .= "
				<tr>
					<td>{$row['id']}</td>
					<td><a href=\"$this->virtualpath{$row['name']}\">{$row['name']}</a></td>
					<td>{$row['size']}</td>
					<td>{$row['type']}</td>
					<td>{$row['title']}</td>
					<td>{$row['updated']}</td>
					<td><form method='post' action='{$_SERVER["REQUEST_URI"]}'>
					<input type='hidden' name='document_id' value='{$row['id']}' />
					<input type='hidden' name='action' value='delete_document' />
					<input type='submit' value='Delete' />
					</form></td>
				</tr>";
				}
				$html = $starttable.$tablecontents.$endtable;
			}
			
		}
		
		// close connection
		$wmfiledb = null;
		
		return $html;
	}
	
	private function uploadFile($title, $files) {
		$uploadname = $files["file"]["name"];
		// replace spaces in file name
		$savename = str_replace(" ", "_", $uploadname);
		// if no title given, use the file name without the extension
		if( strlen($title) === 0 ) $title = substr($uploadname, 0, strrpos($uploadname , '.'));
		
		$size = $files["file"]["size"];
		$type = $files["file"]["type"];
		
		// temporary upload name
		$tmp_name = $files["file"]["tmp_name"];
		
		// error code: http://uk.php.net/manual/en/features.file-upload.errors.php
		$error = $files["file"]["error"];
		
		// file uploaded OK
		if($error == UPLOAD_ERR_OK) {
			// debug
			//return "<p>Uploading '$name' ($type, $size bytes), with title '$title'</p>";
			if(!@move_uploaded_file($tmp_name, $this->uploadpath.$savename)) {
				return "Cannot move uploaded file";
			}
			else {
				// connect to database
				try {
					$wmfiledb = new PDO('sqlite:'.$this->pathtowmfile);
					$wmfiledb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
					
					$uploadedAlready = false;
					
					// http://uk.php.net/manual/en/pdostatement.execute.php
					
					$sql = "SELECT name FROM downloads WHERE name = :name";
					
					// prepare
					$statement = $wmfiledb->prepare($sql);
					if($statement) {
						$statement->bindParam(':name', $savename, PDO::PARAM_STR);
						$statement->execute();
						
						if($statement->fetchAll()) {
							$uploadedAlready = true;
						}
					}
					if($uploadedAlready) {
						$sql = "UPDATE downloads SET size=:size, type=:type, title=:title, updated=:updated WHERE name=:name";
					}
					else {
						$sql = "INSERT INTO downloads (name,size,type,title,updated) VALUES (:name, :size, :type, :title, :updated)";
					}
					
					// prepare
					$statement = $wmfiledb->prepare($sql);
					
					if($statement) {
						// execute with bind parameters: http://uk.php.net/manual/en/pdostatement.bindparam.php
						// PDO constants: http://uk.php.net/manual/en/pdo.constants.php
						$statement->bindParam(':name', $savename, PDO::PARAM_STR);
						$statement->bindParam(':size', $size, PDO::PARAM_INT);
						$statement->bindParam(':type', $type, PDO::PARAM_STR);
						$statement->bindParam(':title', $title, PDO::PARAM_STR);
						$statement->bindParam(':updated', date(DATE_RFC822), PDO::PARAM_STR);
						$statement->execute();
						
						// close connection
						$wmfiledb = null;
						
						if($uploadedAlready) {
							return "File '$savename' has been replaced.";
						}
						else {
							return "New file '$savename' has been uploaded.";
						}
					}
					else {
						// close connection
						$wmfiledb = null;
						
						return "Cannot prepare SQL statement.";
					}
					
				}
				catch(PDOException $e) {
					// close connection
					$wmfiledb = null;
					// delete file
					@unlink($uploadpath.$savename);
					
					return "Exception: ".$e->getMessage();
				}
			}
		}
		else {
			return "Cannot upload file.";
		}
	}
	
	private function deleteFile($id) {
		// connect to database
		try {
			$success = false;
			$wmfiledb = new PDO('sqlite:'.$this->pathtowmfile);
			$wmfiledb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			
			// http://uk.php.net/manual/en/pdostatement.execute.php
			
			$sql = "SELECT name FROM downloads WHERE id = :id";
			
			// prepare
			$statement = $wmfiledb->prepare($sql);
			if($statement) {
				$statement->bindParam(':id', $id, PDO::PARAM_STR);
				$statement->execute();
				
				$record = $statement->fetchAll();
				if(sizeof($record) != 0) {
					$name = $record[0]['name'];
					// delete record
					$sql = "DELETE FROM downloads WHERE id = :id";
					// prepare
					$statement = $wmfiledb->prepare($sql);
					if($statement) {
						$statement->bindParam(':id', $id, PDO::PARAM_STR);
						$statement->execute();
						// delete file
						$success = @unlink($this->uploadpath.$name);
					}
				}
			}
			// close connection
			$wmfiledb = null;
			if($success)
			{
				return "File deleted.";
			}
			else
			{
				return "File could not be deleted.";
			}
		}
		catch(PDOException $e) {
			// close connection
			$wmfiledb = null;
			
			return "Exception: ".$e->getMessage();
		}
	}
	
	public function uploadFormHtml() {
		$html = <<<HTML
<form action='{$_SERVER["REQUEST_URI"]}' method="post" enctype="multipart/form-data" >
	Title: <input name="title" />
	File: <input name="file" type="file" />
	<input type='hidden' name='action' value='upload_document' />
	<input type="submit" value="Upload" />
</form>
HTML;
		
		return $html;
	}
}