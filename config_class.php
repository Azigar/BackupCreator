<?php

class Config{
	
	public $host;
	public $user;
	public $password;
	public $dump_dir;
	public $google_dir;
	public $web_path;
	public $delay_delete;
	public $log_name;
	public $log_cloud;
	public $max_query_length;
	public $index_arr;
	public $db_exception;
	public $site_exception;
	
	public function __construct(){
		$conf = parse_ini_file(__DIR__.'/config.ini');									// читаю config.ini
		$this->db_exception = parse_ini_file(__DIR__.'/db_exception.ini', true);		// читаю db_exception.ini
		$this->site_exception = parse_ini_file(__DIR__.'/site_exception.ini', true);	// читаю site_exception.ini

		$this->host = $conf['host'];													// хост к БД
		$this->user = $conf['user'];													// пользователь БД
		$this->password = $conf['password'];											// пароль этого пользователя

		$this->dump_dir = $conf['dump_dir'];											// папка хранения бэкапов
		$this->google_dir = $conf['google_dir'];										// папка хранения бэкапов на облаке Гугл Диск
		$this->web_path = $conf['web_path'];											// корневая папка для веб-сервера
		$this->delay_delete = $conf['delay_delete'];									// время хранения бэкапов
		$this->log_name = $conf['log_name'];											// путь к лог-файлу
		$this->log_cloud = $conf['log_cloud'];											// путь к лог-файлу копирования на облако
		$this->max_query_length = $conf['max_query_length'];							// максимальная длина запроса

		$this->index_arr = array_map('trim', explode(',', $conf['index']));				// список индексных файлов
	}
}

?>