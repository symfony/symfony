all : 

	HPHP_HOME=/usr/share/hphphome hphp -o hphpout -k 1 -t cpp compile.php
