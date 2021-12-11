<?php require_once __DIR__ . '/handler.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>PHP Terminal</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Courier+Prime&display=swap" rel="stylesheet">
	<link href="./styles.css" rel="stylesheet">
	<script type="text/javascript">
		window.HANDLER = 'handler.php';
		window.CHDIR = '<?php echo $_SESSION['chdir']; ?>';
	</script>
</head>
<body>
	<div class="vh-100 flex justify-center">
		<div class="container flex flex-column w-min-100">
			<div class="title p-10 border-bottom">
				PHP Terminal
			</div>
			<div id="terminal" class="flex-grow p-10 pb-20" contenteditable="true" spellcheck="false"></div>
			<div class="text-light info p-20">
				<p class="m-0"><strong><span id="chdir"></span></strong></p>
				<p class="m-0 mt-5">
					<a id="close" href="?close">close</a>
					&nbsp;|&nbsp;
					<a href="https://github.com/xthukuh" target="_blank">By Thuku</a>
				</p>
			</div>
		</div>
	</div>
	<script type="text/javascript" src="caret.js"></script>
	<script type="text/javascript" src="terminal.js"></script>
	<script type="text/javascript" src="script.js"></script>
</body>
</html>