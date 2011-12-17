<?php
/*
 * Copyright (c) 2011, Lucenko Viacheslav <admin at forum-game dot org>
 * All rights reserved.
 *
 * Redistribution and use in source, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *   * Redistributions of source code must retain the above copyright notice,
 *     this list of conditions and the following disclaimer.
 *   * Neither the name of Redis-PHP nor the names of its contributors may be used
 *     to endorse or promote products derived from this software without
 *     specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */


class RedisPHP {
    /**
     * @var string - resource of database connect
     */
    private $server;
    
    /**
     * @var string - database server connect
     */
    private $hostname = '127.0.0.1';
    
    /**
     * @var integer - server port
     */
    private $port = 6379;

    /**
     * @var integer - Micro seconds sleep
     */
    private $sleeptime = 0;
    
    /**
     * @var string - error container
     */
    private $error = '';
    
    /**
     * @var string - password use
     */
    private $password = null;
     
    /**
     * @var boolean - use blocking socket?
     */
    public $socket_block;
    
    /**
     * @var integer - current database
     */
    public $current_database = 0;
    
    function __construct( $hostname , $port , $password = null, $socket_block = false) 
    {
        $this->hostname = $hostname;
        $this->port = $port;
        $this->password = $password;
        $this->socket_block = $socket_block;
        
        if( ! $this->socket_block )
            $this->connect();
    }

    function __destruct( ) 
    {
        $this->send_message("QUIT");
    }
    
    #########################################################################
    #--------- THIS IS FUNCTIONS TO WORK WITH KEY WITHOUT TYPE -------------#
    #########################################################################
    /**
     * Delete key into db
     * 
     * @param string $key
     * @return integer
     */
    public function del( $key )
    {
        $number = $this->send_message('DEL '.$key);
    }
    
    /**
     * Set expire time in seconds
     * 
     * @param string
     * @param integer
     * @return boolean
     */
    public function expire($key, $time)
    {
        $resp = $this->send_message('EXPIRE '.$key.' '.$time);
        return (bool) $this->int_reply($resp);
    }
    
    /**
     * Set life time key in unix date
     * Only in Redis 2.0.0+
     * Not tested
     * 
     * @param string $key
     * @param integer $time - the unix timestamp
     * @return boolean
     */
    public function unix_expire($key, $time)
    {
        $resp = $this->send_message('EXPIREAT '.$key.' '.$time);
        return (bool) $this->int_reply($resp);
    }
    
    /**
     * Drop life time key
     * 
     * @param string $key
     */
    public function non_expire($key) 
    {
        // TODO
        // Realizate in Redis 2.1.4 when be stable
    }
    
    /**
     * Return life time key
     * 
     * @param string $key
     * @retur integer
     */
    public function ttl($key) 
    {
        $resp = $this->send_message('TTL '.$key);
        return $this->int_reply($resp);
    }
    
    
    
    /**
     * Check isset key
     * 
     * @param string 
     * @return boolean
     */
    public function exists($key)
    {
        return (bool) $this->int_bool_reply( $this->send_message('EXISTS '.$key) );
    }
    
    /**
     * Rename key
     * 
     * @param string $oldkey
     * @param string $newkey
     * @retunr boolean
     */
    public function rename($oldkey, $newkey)
    {
        $resp = $this->send_message('RENAME '.$oldkey.' '.$newkey);
        if( $this->str_bool_reply($resp) == false ) {
            $this->error = $this->str_reply($resp);
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Safely rename key
     * 
     * @param string $oldkey
     * @param string $newkey
     * @return boolean
     */
    public function safely_rename($oldkey, $newkey)
    {
        if( $this->exists($oldkey) ) {
            $this->error = 'Key already exist';
            return false;
        }
        
        return $this->rename($oldkey, $newkey);
    }
    
    /**
     * Return keys number
     * 
     * @return integer
     */
    public function size()
    {
        return $this->int_reply( $this->send_message('DBSIZE') );
    }
    
    /**
     * Flush select database
     * 
     * @return void
     */
    public function flush() 
    {
        $this->send_message('FLUSHDB');
    }
    
    /**
     * Flush all Redis data
     * 
     * @return void
     */
    public function flush_all()
    {
        $this->send_message('FLUSHALL');
    }
    
    /**
     * SELECT database by id
     * 
     * @param integer $id
     * @return boolean
     */
    public function select($id)
    {
        $resp = $this->str_bool_reply( $this->send_message('SELECT '.$id) );
        if( !$resp ) {
            $this->error = $this->str_reply($resp);
            return false;
        }
        $this->current_database = $id;
        return true;
    }
    
    /**
     * Return current database number
     * 
     * @return integer
     */
    public function current_database()
    {
        return $this->current_database;
    }
    
    /**
     * Move the key to the specified database
     * 
     * @param string $key
     * @param integer $id
     * 
     * @return boolean
     */
    public function move($key, $id)
    {
        $resp = $this->send_message('MOVE '.$key.' '.$id);
        return $this->int_bool_reply($resp);
    }
    
    /**
     * Gets all the keys that match the pattern
     * 
     * @params string $patern
     * @return array
     */
    public function keys($pattern)
    {
        $resp = $this->send_message('KEYS '.$pattern);
        $data = explode("\r\n",$resp);
        
        array_pop($data);
        $nums = substr(array_shift($data),1);
        
        $keys = array();
        foreach($data as $key => $val) {
            if( $key % 2 )
                $keys[] = $val;
        }
        
        return $keys;
    }
    
    #########################################################################
    #------------- THIS IS FUNCTIONS TO WORK WITH STRING KEYS --------------#
    #########################################################################
    
    /**
     * Insert new serializate key
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function sset( $key , $value , $expire = false) 
    {
        $data = serialize($value);
        $this->send_message('SET '.$key.' '.strlen($data)."\r\n".$data);
        if( $expire )
            $this->expire($key, $expire);
    }
    
    /**
     * Get serializate key
     * 
     * @param string $key
     * @return mixed
     */
    public function sget( $key ) 
    {
        $response = $this->send_message("GET $key");
        
        list($numb, $data) = explode("\r\n",$response);
        
        if( empty($data) )
            return false;
        
        if($items = @unserialize($data))
            return $items;
        else
            return $data;
    }


    /**
     * Set new key into db
     * 
     * @param string $key
     * @param string $value
     * @return void
     */
    public function set( $key , $value, $expire = false ) 
    {
        $this->send_message('SET '.$key.' '.strlen($value)."\n".$value);
        if( $expire )
            $this->expire($key, $expire);
    }
    
    /**
     * Get key
     * 
     * @param string $key
     * @return string
     */
    public function get( $key ) 
    {
        $response = $this->send_message("GET $key");
        
        list($numb, $data) = explode("\r\n",$response);

        if( empty($data) )
            return false;
        
        return $data;
    }
    
    
    /**
     * Serializate multi get
     * 
     * @param array $keys
     * @return array
     */
    public function smget($mkey)
    {
        $resp = $this->send_message('MGET '.implode(' ',$mkey));
        
        $data = explode("\r\n",$resp);
        
        array_pop($data);
        $nums = substr(array_shift($data),1);
        
        $keys = array();
        $iterate = 0;
        foreach($data as $key => $val) {
            
            if( $key % 2 ) {
                $keys[ $mkey[$iterate++] ] = @unserialize($val);
            }
        }
        
        for( $i = 0; $i < count($mkey); $i++)
            if( !isset($keys[ $mkey[$i] ]) )
                $keys[ $mkey[$i] ] = false;
        
        return $keys;
    }
    
    /**
     * Multy get
     * 
     * @param array $keys
     * @return array
     */
    public function mget($mkey)
    {
        $resp = $this->send_message('MGET '.implode(' ',$mkey));
        
        $data = explode("\r\n",$resp);
        
        array_pop($data);
        $nums = substr(array_shift($data),1);
        
        $keys = array();
        $iterate = 0;
        foreach($data as $key => $val) {
            
            if( $key % 2 ) {
                $keys[ $mkey[$iterate++] ] = $val;
            }
        }
        
        for( $i = 0; $i < count($mkey); $i++)
            if( !isset($keys[ $mkey[$i] ]) )
                $keys[ $mkey[$i] ] = false;
        
        return $keys;
    }
    
    /**
     * Serializate GET and after SET
     * 
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function sgetset($key, $value)
    {
        $data = serialize($value);
        $resp = $this->send_message('GETSET '.$key.' '.strlen($data)."\r\n".$data);
        
        list($numb, $data) = explode("\r\n",$resp);
        
        if( empty($data) )
            return false;
        
        if($items = @unserialize($data))
            return $items;
        else
            return $data;
    }

    /**
     * GET and after SET
     * 
     * @param string $key
     * @param string $value
     * @return string
     */
    public function getset($key, $value)
    {
        $data = serialize($value);
        $resp = $this->send_message('GETSET '.$key.' '.strlen($data)."\r\n".$data);
        
        list($numb, $data) = explode("\r\n",$resp);
        
        if( empty($data) )
            return false;
        
        return $data;
    }
    
    /**
     * Increment method
     * 
     * @param string $key
     * @param integer $num
     * @return integer - new data
     */
    public function incr($key,$num=0)
    {
        $query = 'INCR'.( $num ? 'BY ' : ' ').$key.' '.($num?$num:'' );
        $new_value = $this->send_message($query);
        return str_replace("\r\n",'',substr($new_value,1));
    }

    /**
     * Decrement method
     * 
     * @param string $key
     * @param integer $num
     * @return integer - new data
     */
    public function decr($key,$num=0)
    {
        $query = 'DECR'.( $num ? 'BY ' : ' ').$key.' '.($num ? $num : '' );
        $new_value = $this->send_message($query);
        return str_replace("\r\n",'',substr($new_value,1));
    }

    /**
     * Append string
     * 
     * @param string $key
     * @param string $value
     * @return void
     */
    public function append($key, $value)
    {
        $this->send_message('APPEND '.$key.' '.strlen($value)."\r\n".$value);
    }
    
    /**
     * Return sub string from key
     * 
     * @param string $key
     * @param integer $start
     * @param integer $end
     * @return string
     */
    public function sub_string($key, $start, $end = 100000)
    {
        $resp = $this->send_message('SUBSTR '.$key.' '.$start.' '.$end);
        list($numbet, $data) = explode("\r\n",$resp);
        return $data;
    }
    
    
    ########################################################################
    #------------------------SYSTEM METHODS -------------------------------#
    ########################################################################
    
    /**
     * Error reporting
     * 
     * @return mixed
     */
    public function error() 
    {
        $error = $this->error;
        $this->error = '';
        return empty($error) ? false : $error;
    }
    
    /**
     * Save all data in database
     * 
     * @param boolean $async
     * @return void
     */
    public function save($async = true)
    {
        if( $async )
            $this->send_message('BGSAVE');
        else
            $this->send_message('SAVE');
    }
    
    /**
     * Return last save time
     * 
     * @return void
     */
    public function lastsave()
    {
        return str_replace("\r\n",'',substr( $this->send_message('LASTSAVE') , 1));
    }
    
    /**
     * Transaction start
     * 
     * @return void
     */
    public function multi()
    {
        $this->send_message('MULTI');
    }
    
    /**
     * Exec transaction
     * 
     * @return void
     */
    public function exec()
    {
        $this->send_message('EXEC');
    }
    
    /**
     * Create condition to transaction
     * 
     * @return void
     */
    public function watch( $keys = array() )
    {
        if( $keys )
            $this->send_message('WATCH '.implode(' ',$keys) );
        else
            $this->send_message('UNWATCH');
    }
    
    /**
     * Resets the queue and completes the transaction
     * 
     * @return void
     */
    public function discard()
    {
        $this->send_message('DISCARD');
    }
    
    
    
    
    ########################################################################
    #---------------------------- PRIVATE METHOD  -------------------------#
    ########################################################################

    /**
     * Return string response bool
     * 
     * @param string $str
     * @return boolean
     */
    private function str_bool_reply($str) 
    {
        return substr($str,0,1) == '+';
    }
    
    /**
     * Status code reply
     * 
     * @param string
     * @return boolean
     */
    private function int_bool_reply($str)
    {
        return substr($str,1) == 0 ? false : true;
    }
    
    /**
     * Integer server response
     * 
     * @param string $text
     * @return integer
     */
    private function int_reply($text) 
    {
        return (int) substr($text,1);
    }
    
    /**
     * Return clear response string
     * 
     * @param string $str
     * @return string
     */
    private function str_reply($str)
    {
        $type = $this->str_bool_reply($str);
        
        if( $type )
            return substr($str,1);
        else
            return substr($str,5);
    }
    
    /**
     * Create connect to db
     * 
     * @return void
     */
    private function connect()
    {
        $this->server = @fsockopen( $this->hostname , $this->port );
        
        if( ! $this->server ) {
            $this->error = 'Connect filed to '.$this->hostname.':'.$this->port.'. Redis database server is shutdown';
            throw new RedisException;
            return;
        }
        
        if( $this->socket_block )
            stream_set_blocking( $this->server , 0);
        
        // AUTH if password exists
        if( $this->password ) {
            $resp = $this->send_message('AUTH '.$this->password);
            if( ! $this->str_bool_reply($resp) ) {
                $this->error = $this->str_reply($resp);
                throw new RedisException;
                return;
            }
        }
    }
    
    /**
     * Send socket message to redis
     * 
     * @param string $message
     * @return string
     */
    private function send_message( $message ) 
    {
        if( $this->socket_block )
            $this->connect();
        
        if( $this->server ) {
            fputs( $this->server , $message . "\n\n" );
            
            if( $this->socket_block )
                fclose($this->server);
            
            return $this->read_message();
        }
    }
    
    /**
     * Read socket message
     * 
     * @return string
     */
    private function read_message() 
    {
        if( $this->socket_block )
            $this->connect();
        
        usleep($this->sleeptime);
        
        $line = '';
        do { 
            $line .= fgets($this->server,1024);
            $s = socket_get_status($this->server);
        } while ($s['unread_bytes']);

        if( $this->socket_block )
            fclose( $this->server );
        
        return $line;
    }

}

class RedisException extends Exception {}
