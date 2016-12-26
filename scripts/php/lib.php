<?php

    /*
    Коды:
    200 - OK
    400 - Ошибка при создании сессии
    401 - Ошибка при генерации id сессии
    402 - Ошибка при сохранении сессии
    500 - Ошибка при создании подключения к базе данных
    501 - Ошибка при выполнении запроса к базе данных
    502 - Ошибка при закрытии соединения с базой данных
    600 - Неверный формат входных данных
    601 - Неверный логин/пароль
    602 - Неверный токен
    */

    define("ENCRYPT_METHOD", "AES-256-CTR");  // Метод шифрования для openssl_encrypt
    define("MYSQL_SERVER", "52.169.122.82");  // IP сервера MySQL
    define("MYSQL_LOGIN", "root");  // Логин сервера MySQL
    define("MYSQL_PASSWORD", "5zaU2x8A");  // Пароль сервера MySQL
    define("MYSQL_DB", "camp");  // Название базы данных на сервере MySQL
    define("DB_USERS", "users");  // Таблица со всеми пользователями
    define("DB_TUTORS", "tutors");  // Воспитатели (Дисциплина)|Уровень - 2
    define("DB_STUDENTS", "students");  // Ученики|Уровень - 5
    define("DB_TEACHERS", "teachers");  // Учителя (Учёба, Дисциплина)|Уровень - 3
    define("DB_ORGANIZERS", "organizers");  // Педагоги-Организаторы (Культура, Спорт, Дисциплина)|Уровень - 4
    define("DB_ADMINISTRATORS", "administrators");  // Администраторы сайта|Уровень - 1

    function CheckToken($Token, $Platform){  // Проверка токена возвращает код
        $postData = array("token" => $Token, "platform" => $Platform);
        $Curl = curl_init();
        curl_setopt_array($Curl, array(
            CURLOPT_URL => 'http://forcamptest.azurewebsites.net/scripts/php/authorization.php',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData
        ));
        $response = curl_exec($Curl);
        curl_close($Curl);
        $response = json_decode($response, TRUE);
        return $response['code'];
    }

    function EchoJSON($Array){  // Вывод в формате JSON, на вход массив (Ключ => Значение)
        echo json_encode($Array);
    }

    function EncodeAES($str){
        $SecretKey = "bb62afd41e03935f138221d05aeca1a4847c0c154fad323b3a09c8128054a4d7";
        $Salt = "f59761522aaf0cf9";
        $str = base64_encode($str);
        $EncStr = openssl_encrypt($str, ENCRYPT_METHOD, $SecretKey, OPENSSL_ZERO_PADDING, $Salt);
        return $EncStr;
    }

    function DecodeAES($str){
        $SecretKey = "bb62afd41e03935f138221d05aeca1a4847c0c154fad323b3a09c8128054a4d7";
        $Salt = "f59761522aaf0cf9";
        $str = openssl_decrypt($str, ENCRYPT_METHOD, $SecretKey, OPENSSL_ZERO_PADDING, $Salt);
        $str = base64_decode($str);
        return $str;
    }

    function GetMD5($str){
        try{
            $Salt = "f59761522aaf0cf9";
            $hash = md5(md5($str.$Salt).$Salt);
            return $hash;
        }
        catch (Exception $e){
            return 600;  // 600 - Неверный формат входных данных
        }
    }

    class DataBase{  // Класс для работы с базой данных
        protected $DataBase_Connection;

        protected function BaseInit(){  // Создание подключения к базе данных со стандартными параметрами
            try{
                $Connection = new mysqli(MYSQL_SERVER, MYSQL_LOGIN, MYSQL_PASSWORD, MYSQL_DB);
                return $Connection;
            }catch (Exception $e){
                return 500;  // 500 - Ошибка при создании подключения к базе данных
            }
        }

        protected function AdvancedInit($MySQL_Server, $MySQL_Login, $MySQL_Password, $MySQL_DB){  // Создания подключения к базе данных с заданными параметрами
            try{
                $Connection = new mysqli($MySQL_Server, $MySQL_Login, $MySQL_Password, $MySQL_DB);
                return $Connection;
            }catch (Exception $e){
                return 500;  // 500 - Ошибка при создании подключения к базе данных
            }
        }
    }

    class Authorization_Core extends DataBase{
        var $User_Login = NULL;
        var $User_Password = NULL;
        var $User_Platform = NULL;
        var $User_Token = NULL;
        var $Status = 200;

        function Authorize(){
            if(isset($this->User_Token)){
                return $this->Authorize_WithToken();
            }
            else{
                return $this->Authorize_WithoutToken();
            }
        }

        private function Authorize_WithToken(){
            if($this->CountToken() == 1){
                return $this->Success();
            }
            else{
                return $this->Error(602, "Authorize_WithToken");
            }
        }

        private function Authorize_WithoutToken(){
            if(isset($this->User_Login) and isset($this->User_Password)){
                if($this->CheckAuthInf() == 1){
                    $this->GetToken();
                    if($this->Status == 200){
                        return $this->Success();
                    }
                }
                else{
                    return $this->Error(601, "Authorize_WithoutToken");
                }
            }
            else{
                return $this->Error(600, "CheckAuthInf");  // 600 - Неверный формат входных данных
            }
        }

        private function GetToken(){
            try{
                session_start();
            }
            catch (Exception $e){
                return $this->Error(400, "GetToken");  // 400 - Ошибка при создании сессии
            }
            while (TRUE){
                $Token = $this->GenerateToken(session_id());
                if($this->CountToken($Token) == 0){
                    $this->User_Token = $Token;
                    try{
                        $this->DataBase_Connection->query("UPDATE `".DB_USERS."` SET `".$this->User_Platform."`='$Token' WHERE `Login`='".$this->DataBase_Connection->real_escape_string($this->User_Login)."'");
                        break;
                    }
                    catch (Exception $e){
                        return $this->Error(501, "GetToken");  // 501 - Ошибка при выполнении запроса к базе данных
                    }
                }
            }
            try{
                $_SESSION['Token'] = $this->User_Token;
                session_write_close();
            }
            catch (Exception $e){
                return $this->Error(402, "GetToken");  // 402 - Ошибка при сохранении сессии
            }
        }

        private function GenerateToken($SessionID){
            $Salt = "f59761522aaf0cf9";
            $Token = md5(md5($this->User_Login.$this->User_Password.$this->User_Platform.$Salt).md5(time().md5(rand(0, PHP_INT_MAX))).md5($SessionID));
            return $Token;
        }

        private function Success(){
            $Array = array("status" => "OK", "token" => $this->User_Token, "code" => 200);
            EchoJSON($Array);
            if($this->CloseDataBaseConnection() == 200){
                return 200;  // 200 - OK
            }
            else{
                return 502;
            }
        }

        private function GetUserLogin($Token = NULL){
            if(isset($Token)){
                $Login = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT `Login` FROM `".DB_USERS."` WHERE ".$this->User_Platform."='".$this->DataBase_Connection->real_escape_string($Token)."'"))["Login"] or die($this->Error(501, "GetUserLogin"));  // 501 - Ошибка при выполнении запроса к базе данных
                return $Login;
            }
            else{
                $Login = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT `Login` FROM `".DB_USERS."` WHERE ".$this->User_Platform."='".$this->DataBase_Connection->real_escape_string($this->User_Token)."'"))["Login"] or die($this->Error(501, "GetUserLogin"));  // 501 - Ошибка при выполнении запроса к базе данных
                return $Login;
            }
        }

        private function GetUserOrganization(){  // Получение организации пользователя по ТОКЕНУ
            if(isset($this->User_Token)){
                $Organization = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT `Organization` FROM `".DB_USERS."` WHERE ".$this->User_Platform."='".$this->DataBase_Connection->real_escape_string($this->User_Token)."'"))["Organization"] or die($this->Error(501, "GetUserLogin"));  // 501 - Ошибка при выполнении запроса к базе данных
                return $Organization;
            }
            else{
                return $this->Error(600, "GetUserOrganization");
            }
        }

        private function CheckAuthInf(){
            $Check = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT COUNT('ID') FROM `".DB_USERS."` WHERE `Login`='".$this->DataBase_Connection->real_escape_string($this->User_Login)."' AND `Password`='".$this->DataBase_Connection->real_escape_string($this->User_Password)."'"))["COUNT('ID')"];  //501 - Ошибка при выполнении запроса к базе данных
            return $Check;
        }

        protected function CountToken($Token = NULL){
            if(isset($Token)){
                $Count = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT COUNT('ID') FROM `".DB_USERS."` WHERE ".$this->User_Platform."='".$this->DataBase_Connection->real_escape_string($Token)."'"))["COUNT('ID')"];  // 501 - Ошибка при выполнении запроса к базе данных
                return $Count;
            }
            else{
                $Count = mysqli_fetch_assoc($this->DataBase_Connection->query("SELECT COUNT('ID') FROM `".DB_USERS."` WHERE ".$this->User_Platform."='".$this->DataBase_Connection->real_escape_string($this->User_Token)."'"))["COUNT('ID')"];  // 501 - Ошибка при выполнении запроса к базе данных
                return $Count;
            }
        }

        protected function Error($ErrorCode, $ErrorStage){
            $Array = array("status" => "ERROR", "token" => "", "code" => $ErrorCode);
            EchoJSON($Array);
            $this->Status = $ErrorCode;
            $this->CloseDataBaseConnection();
            return $ErrorCode;
        }

        protected function SetDataBaseConnection(){
            $this->DataBase_Connection = $this->BaseInit();
            if($this->DataBase_Connection == 500){
                return $this->Error(500, "Authorize->SetDataBaseConnection");  // 500 - Ошибка при создании подключения к базе данных
            }
        }

        protected function CloseDataBaseConnection(){
            try{
                $this->DataBase_Connection->Close();
                return 200;
            }
            catch (Exception $e){
                return $this->Error(502, "CloseDataBaseConnection");  // 502 - Ошибка при закрытии соединения с базой данных
            }
        }
    }

    class Authorization_Web extends Authorization_Core{

        function Authorization_Web($Login = NULL, $Password = NULL, $Token = NULL){
            $this->User_Platform = "WEBToken";
            $this->SetDataBaseConnection();
            if(isset($Login) and isset($Password)){
                $this->User_Login = EncodeAES($Login);
                $this->User_Password = GetMD5($Password);
            }
            else{
                if(isset($Token)){
                    $this->User_Token = $Token;
                }
                else{
                    return $this->Error(600, "Authorization_Web");  // 600 - Неверный формат входных данных
                }
            }
        }
    }

    class Authorization_Mobile extends Authorization_Core{

        function Authorization_Mobile($Login = NULL, $Password = NULL, $Token = NULL){
            $this->User_Platform = "MOBILEToken";
            $this->SetDataBaseConnection();
            if(isset($Login) and isset($Password)){
                $this->User_Login = EncodeAES($Login);
                $this->User_Password = GetMD5($Password);
            }
            else{
                if(isset($Token)){
                    $this->User_Token = $Token;
                }
                else{
                    return $this->Error(600, "Authorization_Web");  // 600 - Неверный формат входных данных
                }
            }
        }
    }
?>