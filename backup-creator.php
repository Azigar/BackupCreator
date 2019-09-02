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

echo "\n".date("H:i:s")." Создание локальных бэкапов\n\n";

// подключаю необходимые классы
$conf	= new Config();
$func	= new Functions($conf->log_name);
$zip	= new ZipArchive;

if(php_sapi_name() != 'cli') $func->WriteLog('Это приложение должно быть запущено только из командной строки.', true);

$date = date("Y-m-d");											// текущая дата
$secondsInADay = 60 * 60 * 24;									// к-во секунд в дне
$day_now = floor(time() / $secondsInADay);						// к-во дней на сегодня
$dump_dir = $conf->dump_dir;
$dump_path = "$dump_dir/$date";									// папка текущей даты для хранение локальных бэкапов

if(!is_dir($dump_path)) mkdir($dump_path);						// создаю папку для хранение дампов за текущую дату



/* Удаляем старые логи */
$logFile = $conf->log_name;
if(file_exists($logFile) && is_readable($logFile)){
	echo "\n".date("H:i:s")." Удаляю старые логи\n\n";
	$file = file($logFile);															// читаем файл в массив
	$fp = fopen($logFile, 'w');														// создаем новый файл поверх старого
	for($i = 0; $i < sizeof($file); $i++){											// перебор строк файла в массиве
		if($file[$i] === "\n") unset($file[$i]);									// если пустая строка - удаляю
		else{
			$srt = explode(' ', $file[$i]);											// разбива строку по разделителю "проблел"
			$diff = $day_now - floor(strtotime($srt[0]) / $secondsInADay);			// первый элемент массива - дата лога. Определяю давность лога
			if($diff > $conf->delay_delete) unset($file[$i]);								// если запись старая - удаляю её
			else break;																// в противном случае выхожу с цикла
		}
	}
	fputs($fp, implode('', $file));													// записываю в файл обновенные данные, преобразовав массив в одну строку
	fclose($fp);
}else "Нет доступа к файлу логов $logFile\n";



/* Удаляем старые бэкапы */
if(is_dir($dump_dir)){
	echo "\n".date("H:i:s")." Ищу старые бэкапы\n\n";
	$listDir = $func->listDirectory($dump_dir);
	if(!is_array($listDir)) $func->WriteLog("Нет доступа к папке для хранения бэкапов: $dump_dir");
	else{
		foreach($listDir as $dir){
			$path = "$dump_dir/$dir";
			$fstat = stat($path);												// получаю информацию  о ней
			$diff = $day_now - floor($fstat['ctime'] / $secondsInADay);			// определяю время последней активности текущей папки
			if($diff > $conf->delay_delete){									// если текущая папка с бэкапами старая
				$func->DeleteDirectory($path);									//  - удаляю
				$func->WriteLog("Удалены локальные бэкапы $dump_dir/$obj");
			}
		}
	}
}else $func->WriteLog('Не указана папка для хранения бэкапов.', true);



/* Получаю список всех пользовательских баз данных */
$db = new mysqli($conf->host, $conf->user, $conf->password);
if($db->connect_errno) $func->ConnectionError($db);
else{
	$db->set_charset("utf8"); 											// Устанавливаем кодировку соединения
	$query = "SELECT `schema_name` FROM `information_schema`.`schemata` WHERE `schema_name` NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys', 'phpmyadmin')";
	$result = $db->query($query); 										// Запрашиваю список всех баз данных
	if(!$result) $func->QueryError($db, $query);
	else{
		$db_names = array();											// массив для храниения списка баз данных
		while($data = $result->fetch_assoc()){							// перебираю результат запроса
			if(!$conf->db_exception[$data['schema_name']]['all'])		// если эта БД не находится в полном исключении
				$db_names[] = $data['schema_name'];						// добавляю её в массив
		}
		$result->free();
	}
	$db->close();
}


/* шапка для дампа базы банных */
$start="-- BackupCreator \n-- Автор: Azigar \n-- azigar55@gmail.com \n-- Время создания: ".date("d.m.Y H:i")."\n-- Версия PHP: ".phpversion()."\n-- -------------------------------------------------------- \n";
$mb = $max_query_length / 1048576;
$max_query = "\n-- Максимальная длина запроса: $max_query_length байт = $mb Мб. \n-- -------------------------------------------------------- \n";


/* Создаю архивы баз данных */
if($db_names){
	echo "\n".date("H:i:s")." Создание бэкапов баз данных\n\n";
	$dump_path_db = "$dump_path/databases";
	if(!is_dir($dump_path_db)) mkdir($dump_path_db);															// создаю папку для хранение дампов баз данных за текущую дату
	
	for($i = 0; $i < count($db_names); $i++){																	// пребираю дотупные для бэкапа БД
		$zipFile = "$dump_path_db/{$db_names[$i]}_$date.zip"; 													// полный путь к новому архиву
		
		if(!file_exists($zipFile)){																				// если не существует архива с дампом этой БД
			$db = new mysqli($conf->host, $conf->user, $conf->password, $db_names[$i]); 						// соединяемся с базой данных
			if($db->connect_errno) ConnectionError($db);
			else{					
				$db->set_charset("utf8");																		// устанавливаем кодировку соединения		
				
				$query = "SHOW CREATE DATABASE `{$db_names[$i]}`";												// Запрос на создание базы данных	
				$result = $db->query($query); 																	// получаем запрос на создание базы данных
				if(!$result) $func->QueryError($db, $query);
				else{
					$query = $result->fetch_assoc();															// результат в ассоциативный массив
					$query = array_values($query);																// преобразцовую в индексный массив
					$query = str_replace(' */', '', str_replace('/*!40100 ', '', $query[1]));
					$srt = "$start$max_query\n\n--\n-- База данных: `{$db_names[$i]}`\n--\n{$query};\n";
					$srt.= "USE `{$db_names[$i]}`;\n";															// добавляем результат в строку
					$result->free();
					
					$query = "SHOW TABLES";
					$result = $db->query($query); 																// запрашиваем все таблицы из базы
					if(!$result) $func->QueryError($db, $query);
					else{
						while($table = $result->fetch_assoc()){													// перебор всех таблиц в базе данных
							$table = array_values($table);
							
							$query = "SHOW CREATE TABLE `{$table[0]}`";
							$result_query = $db->query($query); 												// получаем запрос на создание таблицы
							if(!$result_query) $func->QueryError($db, $query);
							else{
								$query = $result_query->fetch_assoc();											// результат в ассоциативный массив
								$query = array_values($query);													// преобразцовую в индексный массив
								$srt.= "\n\n--\n-- Структура таблицы `{$table[0]}`\n--\n{$query[1]};\n";		// добавляем результат в строку
								$result_query->free();
								/*Формирование запроса на вставку данных таблицы*/
								if(!$conf->db_exception[$db_names[$i]][$table[0]]){								// если данные этой таблицы не в исключении
									$query = "SELECT * FROM `{$table[0]}`";
									$result_query = $db->query($query); 										// Получаем все данные из таблицы
									if(!$result_query) $func->QueryError($db, $query);
									else{
										
										$fields = '';
										$values = array();
										$j = 1; $l = 0; $max_len = 0;
										while($rows = $result_query->fetch_assoc()){
											
											$count_fields = count($rows);
											$r = 1;
											$val = '(';
											foreach($rows as $field => $value){
												if($j == 1) $fields .= "`{$field}`";							// получаю список полей
												$val .= "'".$db->real_escape_string($value)."'";				// получаю список значений этого ряда
												if($r < $count_fields){
													$val .= ', ';
													if($j == 1) $fields .= ', ';
												}
												$r++;
											}
											
											$length = strlen($val);											
											if($length > $max_len) $max_len = $length;							// определяю максимальную длину данных в строке
											
											$values[$l] .= $val;
											
											// если длина запроса с "запасом" больше максимальной длины запроса - формирую новый запрос
											if((strlen($fields) + strlen($values[$l]) + 50 + $max_len * 3) >= $max_query_length){
												$values[$l] .= ")";
												$l++;
											}else{
												if($result_query->num_rows > $j) $values[$l] .= "),\n";
												else $values[$l] .= ")";										// если получаю данные последней строки												
											}

											$j++;
										}
										
										$header = "\n\n--\n-- Дамп данных таблицы `{$table[0]}`\n--\n";
										for($l = 0; $l < count($values); $l++){
											$query = "INSERT INTO `{$table[0]}` ({$fields}) VALUES\n{$values[$l]}";
											if(!$l) $srt.= "$header$query;\n";									// добавляем результат в строку
											else $srt.= "$query;\n";											// добавляем результат в строку
										}
										$result_query->free();
									}
								}
							}
						}
						$result->free();
					}
				}
				$db->close();
			}
			if($zip->open($zipFile, ZipArchive::CREATE) === TRUE){				// если архив получилось создать
				$zip->addFromString("{$db_names[$i]}_$date.sql", $srt);			// Создание архива из строки
				$zip->close();
				$func->WriteLog("Создан архив $zipFile");
			}else $func->WriteLog("Ошибка создания архива $zipFile");
		}else $func->WriteLog("$zipFile уже существует.");
	}
}else $func->WriteLog("Базы данных для бэкапа не найдены.");


/* Получаю список всех сайтов (доменов)*/
if($conf->index_arr[0]){
	$web_path = $conf->web_path;
	$sites = array();
	$i = 0;
	if(is_dir($web_path)){
		if($dh = opendir($web_path)){
			while(($domain = readdir($dh)) !== false){													// перебор доменнов
				if(is_dir("$web_path/$domain") && $domain != '.' && $domain != '..'){		
					if($ddh = opendir("$web_path/$domain")){
						while(($site = readdir($ddh)) !== false){										// перебор сайтов (поддометов)
							if(is_dir("$web_path/$domain/$site") && $site != '.' && $site != '..'){
								if($dsh = opendir("$web_path/$domain/$site")){
									if($site === 'www') $site_name = $domain;
									else $site_name = "$site.$domain";								
									/* исключение */
									if(!$conf->site_exception[$site_name]['all']){						// если сайт не неходится в исключении
										while(($file = readdir($dsh)) !== false){
											if(in_array($file, $conf->index_arr)){						// если есть индексный файл - это сайт
												$sites[$i]['domain'] = $site_name;
												$sites[$i]['path'] = "$web_path/$domain/$site";
												$i++;
												break;
											}
										}
									}
									closedir($dsh);
								}else WriteLog("Нет доступа к папке сайта: $web_path/$domain/$site");
							}
						}
						closedir($ddh);
					}else WriteLog("Нет доступа к папке домена: $web_path/$domain");
				}
			}
			closedir($dh);
		}else $func->WriteLog("Нет доступа к корневой папки веб-сервера: $web_path");
	}else $func->WriteLog("Не верно указан путь до корневой папки веб-сервера: $web_path");
}else $func->WriteLog('Список допустимых индексных файлов пустой. Проверьте параметр "index" в "config.ini".');


/* архивирование сайтов */
if($sites){
	echo "\n\n".date("H:i:s")." Создание бэкапов доменов\n\n";
	$dump_path_site = "$dump_path/domains";
	if(!is_dir($dump_path_site)) mkdir($dump_path_site);												// создаю папку для хранение архивов сайтов за текущую дату
	
	for($i = 0; $i < count($sites); $i++){
		$zipFile = "$dump_path_site/{$sites[$i]['domain']}_$date.zip"; 									// полный путь к новому архиву
		
		if(!file_exists($zipFile)){																		// если не существует архива с дампом этой сайта
			$allFiles = $func->getDirContents($sites[$i]['path']);												// получаем список всех файлов в папке сайта с полными путями
			
			if($zip->open($zipFile, ZipArchive::CREATE) === TRUE){										// если архив получилось создать
				foreach($allFiles as $pathFile){														// перебор файлов
					$local = substr($pathFile, strlen($sites[$i]['path'].'/'));							// путь к файлу без полного пути	
					$dirName = dirname($local);															// каталог файла
					$fileName = basename($pathFile);													// имя текущего файла (без путей)
					$exception = $conf->site_exception[$sites[$i]['domain']][$dirName];					// исключение
					
					if($exception){																		// если эта папка в исключении
						if($exception != 1){															// но не полностью
							$files_exception = array_map('trim', explode(',', $exception));
							if(!in_array($fileName, $files_exception)) $zip->addFile($pathFile, $local);
						}
					}else $zip->addFile($pathFile, $local);
				}
				$zip->close;
				$func->WriteLog("Создан архив $zipFile");
			}else $func->WriteLog("Ошибка создания архива $zipFile");
		}else $func->WriteLog("$zipFile уже существует.");									
	}
}else{
	if($index_arr[0]) $func->WriteLog('Сайты для бэкапа не найдены.');
}


echo "\n\n".date("H:i:s")." Создание локальных бэкапов завершено\n\n";
?>
