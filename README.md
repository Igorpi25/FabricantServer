server_v2
=========

Данный проект - это серверная часть реализованная для демонстрации работы следующих Андроид библиотек:

* [Profile][1]
* [Chat][2]
* [Messenger][3]

Установка
---------
Инструкция для самостостоятельной установки и настройки проекта. 

* В файле `include/Config.php` хранятся настройки доступа к базе данных, и также адреса доступа из-вне и локльного хранения изображений. 

  Константа `URL_HOME` используется в качестве обратного адреса сервера, чтобы Android приложение знало откуда нужно скачивать иконки,аватары и т.п. Например, чтобы показать иконку в строке контакта сервер добавляет к `URL_HOME` значение `path_icons`, который является URL путем к расположению файла иконки, затем отправляет это к Android клиенту. Android клиент сохраняет в своей локальной sqlite-БД, и в случае необходимости извлекает из БД URL-путь и передает в [Glid][4].

* В файле `communicator/index.php`, в параметре `$config` нужно указать: номер порта, файл логов, и файл в котором будет харнится PID демона. 

* В фале `index.php` нужно указать номер порта `WEBSOCKET_SERVER_PORT`, по которому внутренний вебсокет-клиент будет обращаться к   вебсокет-серверу. 

  **ВНИМАНИЕ!** Следует указать тот же самый номер порта, который в файле `communicator/index.php` записан в параметре `$config['websocket']`.

* Установить права доступа `777` к директориям `/images`(вложенным директориям тоже) и `/communicator/out`

Логи
----
Автор приложил усилие, чтобы сделать логи наиболее информативным. Каждое сообщение приходящее и уходящее через Communicator оставляет подробный след в логах.

**ВНИМАНИЕ!** Файл логов, со временем может достигать больших размеров (гигабайт в течение месяца). Автор рекомендует использовать логи только во время разработки, в рабочем(боевом) режиме логи следует выключить. 

Чтобы выключить логи зайдите в файл `communicator/WebsocketServer.php` в классе `WebsocketServer` найдите метод `public function log($message)`, и закомментируйте тело функции:

```php
public function log($message){
  //if($this->config['log']){
  //		file_put_contents($this->config['log'], "pid:".posix_getpid()." ".date("Y-m-d H:i:s")." ".$message."\n",FILE_APPEND); 
  //	}
}
```

[1]: https://github.com/Igorpi25/Profile
[2]: https://github.com/Igorpi25/Chat
[3]: https://github.com/Igorpi25/Messenger
[4]: https://github.com/bumptech/glide

