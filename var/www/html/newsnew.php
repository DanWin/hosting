<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<title></title>
<link rel="stylesheet" href="news.css">
</head>
<body>
<?php
error_reporting(E_ALL);
if(!isset($_POST['submit']))
 {
   header("Location: admin.php");
   exit;
 }
          else
          {
           
            $subject = $_POST['subject'];
            $subject = htmlentities($subject);
            $subject = strtoupper($subject);
            $text = $_POST['text'];
            $text = htmlentities($text);
            /*$text = str_replace("/n", "<br>", $text);*/
            $author = $_POST['author'];
            $date = date("d.m.Y");
            $time = date("H:i");
            
            $entry = "$subject|$text|$author|$date|$time";
            if($text == "")
            {
              $file = fopen("../news.txt", "w+");
              fwrite($file, $entry);
              fclose($file);
              
            }
            else
            {
           
              $file = fopen("../news.txt", "w+");
              fwrite($file, $entry);
              fclose($file);
            
            }
          }
 header("Location: admin.php");
?>
</body>
</html> 