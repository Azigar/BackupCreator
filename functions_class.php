<?php

class Functions {
	
	private $logFile;
	
	public function __construct($log){
		$this->logFile = $log;
	}
	
	
	/* функция вернет список лишь первого уровня подпапок по указаному пути */
	public function listDirectory($dir){
		if($dh = opendir($dir)){																	// открываю дескриптор папки для хранения бэкапов
			$listDirs = array();
			while(($obj = readdir($dh)) !== false){													// перебор элементов папки
				if(is_dir("$dir/$obj") && $obj != '.' && $obj != '..') $listDirs[] = $obj; 			// если едемент - папка
			}
			return $listDirs;
			closedir($dh);
		}else return false;
	}
	
	
	public function QueryError($mysqli, $query){
		$this->WriteLog("Ошибка: Не удалось выполнить запрос: \n\n $query \n\n Номер ошибки: ".$mysqli->errno.":".$mysqli->error, true);
	}


	public function ConnectionError($mysqli){
		$this->WriteLog("Ошибка: Не удалось создать соединение с базой MySQL: \n Номер ошибки: ".$mysqli->connect_errno.":".$mysqli->connect_error, true);
	}
	
	
	/* запись сообщений в лог */
	public function WriteLog($mess, $isExit = false){
		$log = $this->logFile;
		$mess = "$mess\n";
		if($log){
			$fp = fopen($log, 'a');
			if($fp){
				fwrite($fp, "\n".date("Y-m-d H:i:s")." $mess");
				fclose($fp);
			}else echo "Нет доступа к файлу $log\n";
		}
		echo "\n$mess";
		if($isExit) exit;
	}
	
	
	///------------------------------------------------------------
	// Ниже представлены три функции для пепебора содержимого папки
	///------------------------------------------------------------

	/* Рекурсивная функция копирование всего содержимого каталога */
	// opendir() - открывает каталог и возвращает его дескриптор
	// @src - путь к папке, содержимое которой хотим скопировать
	// @dst - путь к папке, в которую производится копирование.
	// Если конечной папки нет - она будет создана. Все остальные папки в пути должны существовать 
	public function xCopy($src, $dst){
		$dir = opendir($src);															// если нет такой папки - создаем её
		@mkdir($dst);
		while(false !== ($obj = readdir($dir))){ 
			if(($obj != '.') && ($obj != '..')){ 
				$isCopy = false;
				if(is_dir("$src/$obj")) $this->xCopy("$src/$obj", "$dst/$obj");
				else{
					if(copy("$src/$obj", "$dst/$obj"))								// копируем файл
						$this->WriteLog("На облако успешно скопирован: $dst/$obj");
					
				}
			}
		}
		closedir($dir);
	}


	/* Функция рекурсивного перебора и сохранения всех файлов и папок в массив с использованием */
	// scandir() - возвражает массив файлов и каталогов, расположенных по указанному пути
	// @dir - путь к папке
	public function getDirContents($dir){
		$allFiles = array();
		$files = array_diff(scandir($dir), array('.', '..'));
		foreach($files as $file){
			$pathFile = realpath($dir.DIRECTORY_SEPARATOR.$file);
			if(is_dir($pathFile)) $allFiles = array_merge($allFiles, $this->getDirContents($pathFile));
			else $allFiles[] = $pathFile;
		}
		return $allFiles;
	}


	/* Рекурсивная функция удаление папки со всем содержимым с использование glob(() */
	// glob() - находит файловые пути, совпадающие с шаблоном и возвращает их в виде массива
	// в отличие от opendir() и scandir(), glob() не возвращает объекты '.' и '..'
	// @dir - путь к папке, которую которую нужно удалить
	public function DeleteDirectory($dir){
		$list = glob("$dir/*");
		foreach($list as $obj){
			is_dir($obj) ? $this->DeleteDirectory($obj) : unlink($obj);
		}
		rmdir($dir);
	}
}
	
?>