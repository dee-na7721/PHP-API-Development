<?php

    class DB{

        private static $writeDBConnection;  //static means even if object is not instantiated they will be used
        private static $readDBConnection;   //static->self is used as when class is used these instances are called yetikai
        

        public static function connectWriteDB(){
            if(self::$writeDBConnection === null){
                self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', ''); //db connection establishment with credentials: username='root' & password='root'
                self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);      // windows
                self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
            }
            return self::$writeDBConnection;
        }

        
        public static function connectReadDB(){
            if(self::$readDBConnection === null){
                self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', ''); //db connection establishment with credentials: username='root' & password='root'
                self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                
            }
            return self::$readDBConnection;
        }


    }

?>