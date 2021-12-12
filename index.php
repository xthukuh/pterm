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
		window.WHOAMI = '<?php echo str_replace('\\', '\\\\', $_SESSION['whoami']); ?>';
		window.CWD = '<?php echo $_SESSION['cwd']; ?>';
	</script>
</head>
<body>
	<div class="vh-100 flex justify-center">
		<div class="container flex flex-column">
			<div class="col-light ucase bold p-10 border-bottom">
				PHP Terminal
			</div>
			<div id="terminal" class="flex-grow p-10 pb-20 bg-black col-white overflow-auto pre-wrap" contenteditable="true" spellcheck="false"></div>
			<div class="p-20">
				<p class="m-0">
					<span id="cwd" class="bold"></span>
					&nbsp;|&nbsp;
					<span id="whoami" class="bold"></span>
					&nbsp;
					<span class="float-right col-light">Press Escape to abort commands.</span>
				</p>
				<p class="m-0 mt-5">
					<a id="logout" href="#!">logout</a>
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