<?php include('wmfile.php') ?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>Simple File Manager</title>
    </head>
    <body>
		<h1>Simple File Manager</h1>
		<p>Files are uploaded into the 'files' subdirectory. Files with the same name will be replaced.</p>
		<?php
		$wmfile = new WMFile();
		if(!empty($wmfile->message)) echo $wmfile->message;
		?>
		<h2>Upload file</h2>
		<?php echo $wmfile->uploadFormHtml() ?>
		<h2>Files</h2>
		<?php echo $wmfile->showFiles() ?>
    </body>
</html>
