<?php

	//lib_nikolay_telegram_api.php v 2022-08-29-21-00 https://t.me/skl256
	
	/*Перед использованием необходимо убедиться в наличии модулей php-curl, при необходимости установить: sudo apt-get install php-curl
	
	Использование:
	define("BOT_TOKEN", "1234567890:abcdefghijklmnopqrstuvwxyzABCDEGGHI"); //Указать токен бота от Telegram API
	require_once("lib_nikolay_telegram_api.php"); //Включить в скрипт библиотеку с nikolay_telegram_api
	
	lib_nikolay_telegram_api.php записывает логи с помощью функции writeLog($level, $string),
	для корректной работы необходимо определить данную функцию самостоятельно или воспользоваться примером, расположенном в конце настоящего файла
	
	Доступные методы: (описания методов имеются в комментариях по коду)
	function getUpdate($secret_token);
	function sendMessage($chat_id, $text, $reply_markup = null);
	function sendSticker($chat_id, $filename, $reply_markup = null);
	function sendPhoto($chat_id, $caption, $filename, $reply_markup = null);
	function sendVideo($chat_id, $caption, $filename, $reply_markup = null);
	function sendDocument($chat_id, $caption, $filename, $reply_markup = null);
	function sendMediaGroup($chat_id, $filenames, $captions = null);
	function sendLocation($chat_id, $latitude, $longitude, $horizontal_accuracy = null, $live_period = null, $heading = null, $proximity_alert_radius = null, $reply_markup = null);
	function editMessageLiveLocation($chat_id, $message_id, $latitude, $longitude, $horizontal_accuracy = null, $heading = null, $proximity_alert_radius = null, $reply_markup = null);
	function stopMessageLiveLocation($chat_id, $message_id, $reply_markup = null);
	function sendInlineKeyboard($chat_id, $text, $reply_markup);
	function createInlineKey($text, $callback_data = "");
	function sendChatAction($chat_id, $action = "typing");
	function deleteMessage($chat_id, $message_id);
	function editMessageCaption($chat_id, $message_id, $text, $reply_markup = null);
	function setMyCommands($commands);
	*/
	
	define("USE_CACHE_FOR_TELEGRAM_FILE_ID", true); //При отправке файлов проверять, отправлялся ли данный файл ранее, и отправлять telegram file_id вместо файла
	//!!!функция searchCachedFileId и storeCachedFileId использует файл для хранения кешей и представлена в качестве примера, её необходимо переопределить для стабильной работы и производительности
	//Если вы не хотите использовать кеширование файлов, установите эту опцию как false
	
	function getUpdate($secret_token = "") { //Возвращает JSON поступившего события, проверяет соответсвие HTTP(S)-заголовка X-Telegram-Bot-Api-Secret-Token строке $secret_token, если строка не пуста
		$header_x_telegram_bot_api_secret_token = (isset(getallheaders()['X-Telegram-Bot-Api-Secret-Token'])) ? (getallheaders()['X-Telegram-Bot-Api-Secret-Token']) : ("");
		if (($secret_token == "") || ($secret_token == $header_x_telegram_bot_api_secret_token)) {
			$data = file_get_contents('php://input');//Использование $data = getUpdate();
			writeLog("REQUEST", $data);//Получение содержимого $data['message']['text'] или $data['callback_query']['data'] ...
			return json_decode($data, true);
		} else {
			writeLog("REQUEST", "ACCESS DENIED VIA HTTP(S) HEADER X-Telegram-Bot-Api-Secret-Token = $header_x_telegram_bot_api_secret_token");
			return false;
		}
	}
	
	function sendMessage($chat_id, $text, $reply_markup = null, $disable_notification = null) { //Возвращает ID отправленного сообщения или false в случае ошибки
		writeLog("SEND", "sendMessage($chat_id, " . preg_replace('|(<code>).+(</code>)|isU', "*Data inside <code> block is not logged*", $text) . ", $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['text'] = $text;
		$post_fields['parse_mode'] = "HTML";
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		if ($disable_notification != null) { $post_fields['disable_notification'] = $disable_notification; }
		$response = sendTelegramRequest("sendMessage", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendSticker($chat_id, $filename, $reply_markup = null) { //Возвращает ID отправленного сообщения или false в случае ошибки
		writeLog("SEND", "sendSticker($chat_id, $filename, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$add_header_multipart_form_data = false;
		$post_fields['sticker'] = prepareFileToSend($filename, $add_header_multipart_form_data);
		$response = sendTelegramRequest("sendSticker", $post_fields, $add_header_multipart_form_data);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			cacheFileTelegramFileId(array($filename), $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true, в противном случае
			return $json_response['result']['message_id'];//функция cacheFileTelegramFileId() не будет выполнять никаких действий
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendPhoto($chat_id, $caption, $filename, $reply_markup = null) { //Возвращает ID отправленного сообщения или false в случае ошибки
		writeLog("SEND", "sendPhoto($chat_id, $caption, $filename, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['caption'] = $caption;
		$add_header_multipart_form_data = false;
		$post_fields['photo'] = prepareFileToSend($filename, $add_header_multipart_form_data);
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("sendPhoto", $post_fields, $add_header_multipart_form_data);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			cacheFileTelegramFileId(array($filename), $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true, в противном случае
			return $json_response['result']['message_id'];//функция cacheFileTelegramFileId() не будет выполнять никаких действий
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendVideo($chat_id, $caption, $filename, $reply_markup = null) {
		writeLog("SEND", "sendVideo($chat_id, $caption, $filename, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['caption'] = $caption;
		$add_header_multipart_form_data = false;
		$post_fields['video'] = prepareFileToSend($filename, $add_header_multipart_form_data);
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("sendVideo", $post_fields, $add_header_multipart_form_data);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			cacheFileTelegramFileId(array($filename), $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendDocument($chat_id, $caption, $filename, $reply_markup = null) {
		writeLog("SEND", "sendDocument($chat_id, $caption, $filename, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['caption'] = $caption;
		$add_header_multipart_form_data = false;
		$post_fields['document'] = prepareFileToSend($filename, $add_header_multipart_form_data);
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("sendDocument", $post_fields, $add_header_multipart_form_data);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			cacheFileTelegramFileId(array($filename), $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendMediaGroup($chat_id, $filenames, $captions = null) { //Возвращает ID отправленного сообщения или false в случае ошибки
		$no_captions = false;//Принимает массив файлов для отправки и массив подписей к файлам
		if (($captions == null) || (empty($captions)) || (count($captions) < count($filenames))) {
			$no_captions = true;
		}
		$filenames_log = json_encode($filenames);
		$captions_log = "NO_CAPTIONS";
		if (!$no_captions) {
			$captions_log = json_encode($captions);
		}
		writeLog("SEND", "sendMediaGroup($chat_id, $filenames_log, $captions_log)");
		if (count($filenames) > 10) {
			writeLog("ERROR", "FILENAMES COUNT MUST BE <= 10");
			return false;
		} else {
			$post_fields['chat_id'] = $chat_id;
			$media_items = array();
			$add_header_multipart_form_data = false;
			$attachment_id = 0;
			foreach ($filenames as $filename) {
				$result_is_curl_file = false;
				$type_for_send_media_group = "";
				$file_found = false;
				$prepared_file = prepareFileToSend($filename, $result_is_curl_file, $type_for_send_media_group, $file_found);
				if ($file_found) {
					if ($result_is_curl_file) {
						$add_header_multipart_form_data = true;
						$post_fields["attachment_$attachment_id"] = $prepared_file;
					}
					$media_items[$attachment_id] = array('type' => $type_for_send_media_group, 'media' => ($result_is_curl_file) ? "attach://attachment_$attachment_id" : $prepared_file, 'caption' => (!$no_captions) ? $captions[$attachment_id] : "");
				} else {
					writeLog("ERROR", "FILE NOT EXISTS OR FILESIZE = 0 IN sendMediaGroup(... [ $filename ] ... TIP: sendMediaGroup() can send only local files or files from cache, not url");
				}
				$attachment_id++;
			}
			$post_fields['media'] = json_encode($media_items);
			$response = sendTelegramRequest("sendMediaGroup", $post_fields, $add_header_multipart_form_data);
			$json_response = json_decode($response, true);
			if (!empty($json_response['result']['message_id'])) {
				cacheFileTelegramFileId($filenames, $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true
				return $json_response['result']['message_id'];
			} else if (!empty($json_response['result'][0]['message_id'])) {
				cacheFileTelegramFileId($filenames, $json_response); //Добавление файла в кэш будет работать при USE_CACHE_FOR_TELEGRAM_FILE_ID = true
				return $json_response['result'][0]['message_id'];
			} else {
				writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
				return false;
			}
		}
	}
	
	function sendLocation($chat_id, $latitude, $longitude, $horizontal_accuracy = null, $live_period = null, $heading = null, $proximity_alert_radius = null, $reply_markup = null) {
		writeLog("SEND", "sendLocation($chat_id, $latitude, $longitude, $horizontal_accuracy, $live_period, $heading, $proximity_alert_radius, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['latitude'] = $latitude;
		$post_fields['longitude'] = $longitude;
		if ($horizontal_accuracy != null) { $post_fields['horizontal_accuracy'] = $horizontal_accuracy; }
		if ($live_period != null) { $post_fields['live_period'] = $live_period; }
		if ($heading != null) { $post_fields['heading'] = $heading; }
		if ($proximity_alert_radius != null) { $post_fields['proximity_alert_radius'] = $proximity_alert_radius; }
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("sendLocation", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function editMessageLiveLocation($chat_id, $message_id, $latitude, $longitude, $horizontal_accuracy = null, $heading = null, $proximity_alert_radius = null, $reply_markup = null) {
		writeLog("SEND", "editMessageLiveLocation($chat_id, $message_id, $latitude, $longitude, $horizontal_accuracy, $heading, $proximity_alert_radius, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['message_id'] = $message_id;
		$post_fields['latitude'] = $latitude;
		$post_fields['longitude'] = $longitude;
		if ($horizontal_accuracy != null) { $post_fields['horizontal_accuracy'] = $horizontal_accuracy; }
		if ($heading != null) { $post_fields['heading'] = $heading; }
		if ($proximity_alert_radius != null) { $post_fields['proximity_alert_radius'] = $proximity_alert_radius; }
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("editMessageLiveLocation", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function stopMessageLiveLocation($chat_id, $message_id, $reply_markup = null) {
		writeLog("SEND", "stopMessageLiveLocation($chat_id, $message_id, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['message_id'] = $message_id;
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("stopMessageLiveLocation", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['result']['message_id'])) {
			return $json_response['result']['message_id'];
		} else {
			writeLog("ERROR", "MESSAGE ID IS EMPTY IN $response");
			return false;
		}
	}
	
	function sendInlineKeyboard($chat_id, $text, $reply_markup) { //Возвращает ID отправленного сообщения или false в случае ошибки
		writeLog("SEND", "sendInlineKeyboard($chat_id, $text, $reply_markup) using sendMessage()");//Принимает JSON InlineKeyboard например 
		return sendMessage($chat_id, $text, $reply_markup);//sendInlineKeyboard(ADMIN_CHAT_ID, "Pets", createInlineKeyboard(createInlineKey("Cat", "Cat"), createInlineKey("Pig", "Pig")));
	}
	
	function createInlineKey($text, $callback_data = "") {//Создаёт кнопку для использования в InlineKeyboard
		if ($callback_data == "") {
			$callback_data = $text;
		}
		$key = array(array("text" => $text, "callback_data" => $callback_data));
		return $key;
	}
	
	function createInlineKeyboard(...$keys) {//Создаёт JSON InlineKeyboard из произвольного кол-ва InlineKey готовый к отправке
		if(!empty($keys)) {
			$inline_keyboard = array("inline_keyboard" => $keys);
			$inline_keyboard_json = json_encode($inline_keyboard);
			return $inline_keyboard_json;
		} else {
			return false;
		}
	}
	
	function sendChatAction($chat_id, $action = "typing") { //Возвращает ID отправленного сообщения или false в случае ошибки
		writeLog("SEND", "sendChatAction($chat_id, $action)"); //typing, upload_photo, record_video, upload_video,
		$post_fields['chat_id'] = $chat_id;//record_voice, upload_voice, upload_document, choose_sticker, find_location, record_video_note, upload_video_note
		$post_fields['action'] = $action;
		$response = sendTelegramRequest("sendChatAction", $post_fields, false);
		$json_response = json_decode($response, true);
		if ((!empty($json_response['ok'])) && ($json_response['ok'] == "true")) {
			return true;
		} else {
			writeLog("ERROR", "RESPONSE IS NOT OK IN $response");
			return false;
		}
	}
	
	function deleteMessage($chat_id, $message_id) { //Возвращает true при успешном удалении или false в случае ошибки
		writeLog("SEND", "deleteMessage($chat_id, $message_id)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['message_id'] = $message_id;
		$response = sendTelegramRequest("deleteMessage", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['ok'])) {
			if ($json_response['ok'] == "true") {
				return true;
			} else {
				writeLog("ERROR", "STATUS NOT TRUE IN $response");
				return false;
			}
		} else {
			writeLog("ERROR", "STATUS OK IS EMPTY OR NOT TRUE IN $response");
			return false;
		}
	}
	
	function editMessageCaption($chat_id, $message_id, $text, $reply_markup = null) { //Возвращает true при успешном изменении или false в случае ошибки
		writeLog("SEND", "editMessageCaption($chat_id, $message_id, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['message_id'] = $message_id;
		$post_fields['caption'] = $text;
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("editMessageCaption", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['ok'])) {
			if ($json_response['ok'] == "true") {
				return true;
			} else {
				writeLog("ERROR", "STATUS NOT TRUE IN $response");
				return false;
			}
		} else {
			writeLog("ERROR", "STATUS OK IS EMPTY OR NOT TRUE IN $response");
			return false;
		}
	}
	
	function editMessageReplyMarkup($chat_id, $message_id, $reply_markup = null) { //Возвращает true при успешном изменении или false в случае ошибки
		writeLog("SEND", "editMessageReplyMarkup($chat_id, $message_id, $reply_markup)");
		$post_fields['chat_id'] = $chat_id;
		$post_fields['message_id'] = $message_id;
		if ($reply_markup != null) { $post_fields['reply_markup'] = $reply_markup; }
		$response = sendTelegramRequest("editMessageReplyMarkup", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['ok'])) {
			if ($json_response['ok'] == "true") {
				return true;
			} else {
				writeLog("ERROR", "STATUS NOT TRUE IN $response");
				return false;
			}
		} else {
			writeLog("ERROR", "STATUS OK IS EMPTY OR NOT TRUE IN $response");
			return false;
		}
	}
	
	function setMyCommands($commands) { //Возвращает true при успешной установке или false в случае ошибки
		$commands_json = json_encode($commands);//формат $commands = array(array("command" => "start", "description" => "Старт"), array("command" => "help", "description" => "Помощь"));
		writeLog("SEND", "setMyCommands(JSON: $commands_json)");
		$post_fields['commands'] = $commands_json;
		$response = sendTelegramRequest("setMyCommands", $post_fields, false);
		$json_response = json_decode($response, true);
		if (!empty($json_response['ok'])) {
			if ($json_response['ok'] == "true") {
				return true;
			} else {
				writeLog("ERROR", "STATUS NOT TRUE IN $response");
				return false;
			}
		} else {
			writeLog("ERROR", "STATUS OK IS EMPTY OR NOT TRUE IN $response");
			return false;
		}
	}
	
	//Методы ниже, являются внутренними, и необходимы для работы основных методов lib_nikolay_telegram_api.php
	
	function typeForSendMediaGroup($filename) {//Возвращает тип файла пригоднгый для указания в методе sendMediaGroup, поддерживаются photo, video, audio, document
		if(file_exists($filename)) {
			$mime_content_types = explode("/", mime_content_type($filename));
			if (!empty($mime_content_types)) {
				switch ($mime_content_types[0]) {
					case "image" : return "photo"; break;
					case "video" : return "video"; break;
					case "audio" : return "audio"; break;
					default : return "document";
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function sendTelegramRequest($method, $post_fields, $add_header_multipart_form_data = false) {//Выполняет запрос к методу $method TELEGRAM API и возвращает ответ от API в виде JSON
		$ch = curl_init();//Необходимо указывать $add_header_multipart_form_data = true при загрузке файлов непосредственно в запросе
		$ch_post[CURLOPT_URL] = 'https://api.telegram.org/bot' . BOT_TOKEN . "/$method";//пример заполнения поля для загрузки файлов:
		$ch_post[CURLOPT_POST] = true;//'photo' => curl_file_create($filename , mime_content_type($filename), basename($filename))
		$ch_post[CURLOPT_RETURNTRANSFER] = true;
		$ch_post[CURLOPT_TIMEOUT] = 10;
		if ($add_header_multipart_form_data) {
			$ch_post[CURLOPT_HTTPHEADER] = array("Content-Type" => "multipart/form-data");
		}
		$ch_post[CURLOPT_POSTFIELDS] = $post_fields;
		curl_setopt_array($ch, $ch_post);
		$response = curl_exec($ch);
		writeLog("SEND", $response);
		return $response;
	}
	
	function prepareFileToSend($filename, &$result_is_curl_file = null, &$type_for_send_media_group = null, &$file_found = null, &$file_in_cache = null) {//Подготовливает файл к отправке, возвращает
		$cached_file_id = (USE_CACHE_FOR_TELEGRAM_FILE_ID) ? (searchCachedFileId($filename, $type_for_send_media_group)) : (false);//либо curl_file либо file_id для telegram
		if (!$cached_file_id) {//также задаёт значения переданным по ссылкам переменным, которые могут использоваться в дальнейшем
			if ((file_exists($filename)) && (filesize($filename) > 0)) {
				$file_found = true;
				$file_in_cache = false;
				$result_is_curl_file = true;
				$type_for_send_media_group = typeForSendMediaGroup($filename);
				writeLog("SEND", "prepareFileToSend($filename) : SEND LOCAL FILE, FILE FOUND IN LOCAL FILESYSTEM, FILESIZE IS " . filesize($filename));
				$filename = realpath($filename);
				return curl_file_create($filename , mime_content_type($filename), basename($filename)); //Файл найден локально, возвращаем curl_file
			} else {
				$file_found = false;
				$file_in_cache = false;
				$result_is_curl_file = false;
				//$type_for_send_media_group = null значение останется null, т.к. мы не нашли файл и всего лишь предполагаем, что $filename это telegram file_id
				writeLog("SEND", "prepareFileToSend($filename) : FILE NOT FOUND, TRY TO USE AS FILE_ID FROM TELEGRAM SERVER OR AS URL");
				return $filename; //Файл не найден ни локально ни в кэше, можем предоположить что $filename это telegram file_id, возвращаем исходную строку - $filename
			}
		} else {
			$file_found = true;
			$file_in_cache = true;
			$result_is_curl_file = false;
			//$type_for_send_media_group = $type_for_send_media_group, значение должно быть присвоено функцией searchCachedFileId
			writeLog("SEND", "prepareFileToSend($filename) : FILE IS FOUND IN CACHE, FILE_ID FROM TELEGRAM SERVER = $cached_file_id");
			writeLog("CACHE", "FOUND: REQUEST = $filename RESPONSE TYPE = $type_for_send_media_group TELEGRAM_ID = $cached_file_id");
			return $cached_file_id; //Файл найден в кэше, возвращаем telegram file_id
		}
	}
	
	function cacheFileTelegramFileId($filenames, $json_response) { //Разбирает ответ сервера telegram после отправки сообщения, находит в нём file_id отправленных
		if (USE_CACHE_FOR_TELEGRAM_FILE_ID) {//файлов и записывает в кеш
			$filetypes = array();//Для корректной работы необходимо передавать в фунеуию массив отправленных файлов и ассоциативнй массив ответа сервера
			$file_ids = array();
			if(!empty($json_response)) {
				if (isset($json_response['result'])) {
					$result_array = array();
					if (isset($json_response['result']['message_id'])) {
						$result_array = array($json_response['result']);
					} else if (isset($json_response['result'][0]['message_id'])) {
						$result_array = $json_response['result'];
					}
					if (!empty($result_array)) {
						foreach ($result_array as $result) {
							foreach($result as $key => $value) {
								if (isset($value['file_id'])) {
									$filetypes[] = $key;
									$file_ids[] = $value['file_id'];
								} else if (isset($value[0]['file_id'])) {
									$filetypes[] = $key;
									$file_ids[] = $value[0]['file_id'];
								}
							}
						}
					}
				}
			}
			if (count($filenames) == count($file_ids)) {
				for ($i = 0; $i < count($filenames); $i++) {
					if (storeCachedFileId($filenames[$i], $file_ids[$i], $filetypes[$i])) {
						writeLog("CACHE", "CACHED: FILENAME = $filenames[$i] TYPE = $filetypes[$i] TELEGRAM_ID = $file_ids[$i]");
					}
				}
			} else {
				writeLog("ERROR", "CACHE ERROR: COUNT(FILENAMES) != COUNT(FILEIDS) (" . count($filenames) . " != " . count($file_ids) . ")");
			}
		}
	}
	
	//Методы, которые рекоммендуется переопределить в целях улучшения стабильности или производительности
	//В библиотеке lib_nikolay_telegram_api.php нет реализации данных методов для применения в продуктивной среде, так как это создало бы доп. требования к совместимости,
	//например, необходимость использования определенной БД. Вместо этого, использующий библиотеку сможет самостоятельно определить способ хранения данных, либо отключить
	//кеширование файлов установив define("USE_CACHE_FOR_TELEGRAM_FILE_ID", false);
	
	define("DO_NOT_USE_IN_PRODUCTION_CACHE_DIR", "cache/"); // / at the end is required
	if (!is_dir(DO_NOT_USE_IN_PRODUCTION_CACHE_DIR)) { mkdir(DO_NOT_USE_IN_PRODUCTION_CACHE_DIR, 0777, true); }
	//define("DO_NOT_USE_IN_PRODUCTION_CACHE_FILE_NAME", "lib_nikolay_telegram_api.php.FILE_CACHE_some_secret_string");
	
	function searchCachedFileId($filename, &$type_for_send_media_group = null) { //(!!!)Шаблон функции
		$cache_file_name = DO_NOT_USE_IN_PRODUCTION_CACHE_DIR . DO_NOT_USE_IN_PRODUCTION_CACHE_FILE_NAME;//Функция должна производить поиск по $filename (это может быть имя файла, url или file_id telegram) и возвращать file_id файла а так-же присваивать совместиый с telegram api тип медиа
		if (file_exists($cache_file_name)) {
			$lines = file($cache_file_name);
			foreach ($lines as $line) {
				$params = explode(";", $line);
				if (count($params) == 4) {
					if (($params[0] == $filename) || ($params[1] == $filename)) {
						$type_for_send_media_group = $params[2];
						return $params[1];
					}
				}
			}
		}
		return false;
	}
	
	function storeCachedFileId($filename, $file_id, $type_for_send_media_group = null) { //(!!!)Шаблон функции
		if (!searchCachedFileId($filename)) {//Функция должна записывать $filename (это может быть имя файла, url) $file_id и совместиый с telegram api тип медиа
			$cache_file_name = DO_NOT_USE_IN_PRODUCTION_CACHE_DIR . DO_NOT_USE_IN_PRODUCTION_CACHE_FILE_NAME;
			$line = "$filename;$file_id;$type_for_send_media_group;---\n";
			if (file_put_contents($cache_file_name, $line, FILE_APPEND)) {
				return true;
			}
		}
		return false;
	}
	
	/*Пример настройки логгирования и определения функции writeLog()
	
	define("LOG_PATH_SALT", "01234567890abcdef"); //Указать секректную строку, которая будет добавляться к имени .txt файла лога, для затруднения несанционированного доступа
		//к файлу через HTTP
	ini_set("log_errors", "On");
	ini_set("error_log", "log_PHP_" . LOG_PATH_SALT . ".txt");
	
	function writeLog($level, $string) {
		$line = date("Y-m-d H:i:s", time()) . " " . $level . ": " . htmlspecialchars($string, ENT_NOQUOTES) . "\n";
		file_put_contents("log_MAIN_" . LOG_PATH_SALT . ".txt", $line, FILE_APPEND);
		file_put_contents("log_" . $level . "_" . LOG_PATH_SALT . ".txt", $line, FILE_APPEND);
	}
	*/
?>