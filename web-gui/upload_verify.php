<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Upload Verifier</title>
  <style>
  body {
    font-size: 12px;
    font-family: Arial;
  }
  </style>
  <script src="//code.jquery.com/jquery-1.10.2.js"></script>
</head>

<body>
 
<div>
<button id="btnLoad">Verify Uploads</button>
</div>

<div id="content">
  Please click button to run test.<br />
  <b>This is slow, please be patient.</b>
</div>

<div id="status">
</div>

<script type="text/javascript">
  $("#btnLoad").click(function(){
  		$("#content").html( "<b>Working...</b>");
		  // Make AJAX call
		  $("#content").load("/shop2/_upload_verify.php");
		  });
</script>

</body>
</html>
