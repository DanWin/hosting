<?php
$head=true;
while($line=fgets(STDIN)){
	if($head && stripos(ltrim($line), 'FROM:')===0){
		continue;
	}
	if($head && ($line==="\r\n" || $line==="\n")){
		$head=false;
		echo "From: $argv[1]\r\n";
	}
	echo $line;
}
