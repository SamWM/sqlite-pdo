<?php
/**
 * Simple file manager class
 *
 * @version    1.3
 * @author     Sam Collett
 * @license    http://github.com/SamWM/sqlite-pdo/blob/master/LICENSE
 */
class WMFile
{
	private $pathtowmfile = "wmfile.sqlite";
	public $message;
	
	// the directory of the script including this
	private $callingdir;
	private $uploadpath;
	private $virtualpath;

	// PDO object to connect to database
	private $wmfiledb;
	
	// remote user is empty if not logged in
	private $remoteuser = "";
	
	function __construct()
	{
		$this->callingdir = dirname($_SERVER["SCRIPT_FILENAME"]);
		$this->uploadpath = $this->callingdir."/files/";
		$this->virtualpath = str_replace($_SERVER["DOCUMENT_ROOT"], "", $this->uploadpath);
		// get user from server variable
		if (isset($_SERVER["REDIRECT_REMOTE_USER"])) $this->remoteuser = $_SERVER["REDIRECT_REMOTE_USER"];
		elseif (isset($_SERVER["REMOTE_USER"]))	$this->remoteuser = $_SERVER["REMOTE_USER"];

		// PDO http://uk.php.net/manual/en/class.pdo.php
		$this->wmfiledb = new PDO('sqlite:'.$this->pathtowmfile);
		$this->wmfiledb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		// upload file
		if($_SERVER["REQUEST_METHOD"] == "POST")
		{
			$title = $_POST["title"];
			$action = $_POST["action"];
			if(is_string($title) && is_array($_FILES) && $action == 'upload_document')
			{
				$this->message = $this->uploadFile($title, $_FILES);
			}
			if($action == "delete_document")
			{
				$this->message = $this->deleteFile($_POST["document_id"]);
			}
		}
	}

	function __destruct()
	{
		$this->wmfiledb = null;
	}

	/**
	 * Convert bytes into kilobytes, megabytes etc
	 *
	 * @param int $size Size in bytes
	 * @param int $dec Round to this many decimal places
	 * @return string Size in appropriate format (KB, MB etc)
	 */
	public static function convertBytes($size, $dec = 2)
	{
		if(!is_int($dec)) $dec = 2;
		$multiply = pow(10, $dec);
		$units = array('B','KB','MB','GB','TB','PB');
		$exponent = floor(log($size) / log(1024));
		return round(
			($size / pow(1024, $exponent) * $multiply) / $multiply, $dec
		).$units[$exponent];
	}

	/**
	 * Shows a table listing the files set in the database
	 *
	 * @param boolean $admin Show admin options (delete button)
	 * @return string An html table file listing
	 */
	public function showFiles($admin = true)
	{
		$html = "No files found";
		
		$starttable = "<table>
	<tr>"
		.(($admin == true) ? "<th>id</th>" : "").
		"<th>name</th>
		<th>size</th>
		<th>type</th>
		<th>title</th>
		<th>updated</th>"
		.(($admin == true) ? "<th>delete</th>" : "").
	"</tr>";
		$endtable = "</table>";
		$tablecontents = "";
		$statement = $this->wmfiledb->query("SELECT * FROM downloads");
		if(!$statement)
		{
			$html = "Table 'downloads' does not exist in database";
		}
		else
		{
			$record = $statement->fetchAll();
			if(sizeof($record) != 0)
			{
				foreach ($record as $row)
				{
					$tablecontents .=
					"<tr>"
						.(($admin == true) ? "<td>{$row['id']}</td>" : "").
						"<td><a href=\"$this->virtualpath{$row['name']}\">{$row['name']}</a></td>
						<td>".WMFile::convertBytes($row['size'])."</td>
						<td>{$row['type']}</td>
						<td>{$row['title']}</td>
						<td>{$row['updated']}</td>"
						.(($admin == true) ? "
						<td><form method='post' action='{$_SERVER["REQUEST_URI"]}'>
						<input type='hidden' name='document_id' value='{$row['id']}' />
						<input type='hidden' name='action' value='delete_document' />
						<input type='submit' value='Delete' />
						</form></td>" : "").
					"</tr>";
				}
				$html = $starttable.$tablecontents.$endtable;
			}
		}
		return $html;
	}
	
	private function uploadFile($title, $files)
	{
		$uploadname = $files["file"]["name"];
		// replace spaces in file name
		$savename = str_replace(" ", "_", $uploadname);
		// if no title given, use the file name without the extension
		if( strlen($title) === 0 ) $title = substr($uploadname, 0, strrpos($uploadname , '.'));
		// file size
		$size = $files["file"]["size"];
		// content type
		$type = $files["file"]["type"];
		
		// temporary upload name
		$tmp_name = $files["file"]["tmp_name"];
		
		// error code: http://uk.php.net/manual/en/features.file-upload.errors.php
		$error = $files["file"]["error"];
		
		// file uploaded OK
		if($error == UPLOAD_ERR_OK)
		{
			// debug
			//return "<p>Uploading '$name' ($type, $size bytes), with title '$title'</p>";
			if(!@move_uploaded_file($tmp_name, $this->uploadpath.$savename))
			{
				return "Cannot save uploaded file.";
			}
			else
			{
				try
				{
					$uploadedAlready = false;
					$sql = "SELECT name FROM downloads WHERE name = :name";
					// prepare
					$statement = $this->wmfiledb->prepare($sql);
					if($statement)
					{
						$statement->bindParam(':name', $savename, PDO::PARAM_STR);
						$statement->execute();
						if($statement->fetchAll())
						{
							$uploadedAlready = true;
						}
					}
					if($uploadedAlready)
					{
						$sql = "UPDATE downloads SET size=:size, type=:type, title=:title, updated=:updated WHERE name=:name";
					}
					else
					{
						$sql = "INSERT INTO downloads (name, size, type, title, updated) VALUES (:name, :size, :type, :title, :updated)";
					}
					// prepare
					$statement = $this->wmfiledb->prepare($sql);
					if($statement)
					{
						// execute with bind parameters: http://uk.php.net/manual/en/pdostatement.bindparam.php
						// PDO constants: http://uk.php.net/manual/en/pdo.constants.php
						$statement->bindParam(':name', $savename, PDO::PARAM_STR);
						$statement->bindParam(':size', $size, PDO::PARAM_INT);
						$statement->bindParam(':type', $type, PDO::PARAM_STR);
						$statement->bindParam(':title', $title, PDO::PARAM_STR);
						$statement->bindParam(':updated', date(DATE_RFC822), PDO::PARAM_STR);
						$statement->execute();
						if($uploadedAlready)
						{
							return "File '$savename' has been replaced.";
						}
						else
						{
							return "New file '$savename' has been uploaded.";
						}
					}
					else
					{
						return "Cannot prepare SQL statement.";
					}
					
				}
				catch(PDOException $e)
				{
					// delete file if database not updated
					@unlink($this->uploadpath.$savename);
					
					return "Exception: ".$e->getMessage();
				}
			}
		}
		else
		{
			return "Cannot upload file.";
		}
	}

	/**
	 * Delete file matching id in database.
	 * @param integer $id The id of the record containing the file to be deleted
	 * @return string Success or failure message
	 */
	private function deleteFile($id)
	{
		try
		{
			$success = false;
			$sql = "SELECT name FROM downloads WHERE id = :id";
			// prepare
			$statement = $this->wmfiledb->prepare($sql);
			if($statement)
			{
				$statement->bindParam(':id', $id, PDO::PARAM_STR);
				$statement->execute();
				$record = $statement->fetchAll();
				if(sizeof($record) != 0)
				{
					$name = $record[0]['name'];
					// delete record
					$sql = "DELETE FROM downloads WHERE id = :id";
					// prepare
					$statement = $this->wmfiledb->prepare($sql);
					if($statement)
					{
						$statement->bindParam(':id', $id, PDO::PARAM_STR);
						$statement->execute();
						// delete file
						$success = @unlink($this->uploadpath.$name);
					}
				}
			}
			if($success)
			{
				return "File deleted.";
			}
			else
			{
				return "File could not be deleted.";
			}
		}
		catch(PDOException $e)
		{
			return "Exception: ".$e->getMessage();
		}
	}
	
	public function uploadFormHtml()
	{
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