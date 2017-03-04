xcopy \dev\github\adodb-master\*.php \dev\github\sddata /S /Y
rem copy \dev\github\adodb-master\adodb-active-record.inc.php \dev\github\sddata\adodb-active-record.inc.php
rem call phpcbf --standard=PEAR --no-patch \dev\github\sddata
rem call phpcs --standard=PEAR \dev\github\sddata \dev\github\standards\csout.txt
call phpcbf --standard=PEAR --no-patch \dev\github\sddata\adodb-active-record.inc.php
call phpcs --standard=PEAR \dev\github\sddata\adodb-active-record.inc.php \dev\github\standards\csout.txt
call php \dev\github\standards\insert-header-docblock.php
call php \dev\github\standards\insert-class-docblocks.php
call php \dev\github\standards\insert-function-docblocks.php
call php \dev\github\standards\insert-method-docblocks.php
cd \dev\github\standards