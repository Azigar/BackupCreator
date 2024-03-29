#!/usr/bin/php
<?php
echo "\n";
set_time_limit(0); 				// Убираем ограничение на максимальное время работы скрипта
mb_internal_encoding("UTF-8");	// Устанавливаем внутреннюю кодировку символов.

// я не использую жесткие проверки переменных - isset(), empty() и unset()
ini_set('display_errors', 1);	// убираем Notice: 	
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once __DIR__.'/config_class.php';
require_once __DIR__.'/functions_class.php';

// подключаю необходимые классы
$conf	= new Config();
$func	= new Functions($conf->log_name);

if(php_sapi_name() != 'cli') $func->WriteLog('Это приложение должно быть запущено только из командной строки.', true);

$dump_dir = $conf->dump_dir;										// папка хранения бэкапов
$google_dir = $conf->google_dir;
$date = date("Y-m-d");												// текущая дата

echo "\n".date("H:i:s")." Синхронизация с облаком\n\n";

$listLocal = $func->listDirectory($dump_dir);						// получаю список локальных каталогов с бэкапами							
$listCloud = scandir($google_dir);									// список облачных каталогов с бэкапами

if($listLocal && is_array($listLocal)){
	if($listCloud && is_array($listCloud)){
		foreach($listCloud as $dir){
			if(($dir != '.') && ($dir != '..')){
				if(!in_array($dir, $listLocal)){
					$func->DeleteDirectory(realpath($google_dir.DIRECTORY_SEPARATOR.$dir));
					$func->WriteLog("Удалены облачные бэкапы $dir");
				}
			}
		}
		
		if(is_dir("$dump_dir/$date")){
			$func->xCopy("$dump_dir/$date", "$google_dir/$date");							// копирование на облако новых бэкапов
		}else $func->WriteLog('Бэкапы за $date еще не созданы.', true);
	}else $func->WriteLog('Нет доступа к облачной папке для хранения бэкапов.', true);
}else $func->WriteLog('Нет доступа к локальной папке для хранения бэкапов.', true);

echo "\n\n".date("H:i:s")." Синхронизация завершена\n\n";
?>
