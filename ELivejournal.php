<?php

/**
 * Livejournal extension for the Yii 1.x framework
 *
 * @author  Artem Frolov <artem@frolov.net>
 * @license MIT https://opensource.org/licenses/MIT
 * @link    http://frolov.net/
 *
 * Requirements
 * - Yii 1.0, 1.1 or above
 * - Enabled XML-RPC support in PHP
 *
 * Copyright (C) 2011 by Artem Frolov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/*
Simple usage:

Yii::import('ext.livejournal.*');
$post = new ELivejournal('username', 'password');
$post->subject = 'Subject test';
$post->body = 'Hello, <b>world</b>';
if ($post->save())
{
    echo "Entry's id: ".$post->id;
    echo "<br />Entry's url: ".$post->url;
}
else
{
    echo '<b>Error (code '.$post->errorCode.'): </b>';
    echo $post->error;
}

=======
Advanced usage:

Yii::import('ext.livejournal.*');
$post = new ELivejournal('username', 'md5_hash_of_the_password', true);

//Use the next line if you want to update the entry with specific id
//$post->id = 2;

$post->subject = 'Subject test';
$post->body = 'Hello, <b>world</b>';

//Entry's time will be 'now' + 1 week
$post->time = time() + 60*60*24*7;

//Entry's tags will be 'red,green,blue'
$post->tags = array('red','green');
$post->addTag('blue');

//Entry's properties from the http://www.livejournal.com/doc/server/ljp.csp.proplist.html
//current music
$post->setMetadata('current_music','Muse - Butterflies and hurricanes');
//Comments will be disabled
$post->setMetadata('opt_nocomments',true);

//Entry will be visible only for friends
$post->setPrivate();

//Turns on \r and \n removing from the entry's body
//Sometimes it's usable because the Livejournal.com translate new lines to the <br>
$post->setDeleteNewLines();

if ($post->save())
{
    echo "Entry's id: ".$post->id;
    echo "<br />Entry's url: ".$post->url;
}
else
{
    echo '<b>Error (code '.$post->errorCode.'): </b>';
    echo $post->error;
}

*/

/**
 * ELivejournal class to create and update posts on LiveJounral.com
 */
class ELivejournal extends CComponent
{

    /**
     * Array with entry's information
     * 
     * @var array
     */
    protected $data = array(
        'auth_method' => 'challenge',
        'security' => 'public',
        'lineendings' => 'unix',
        'ver' => 1
    );

    /**
     * Configuration of XML-RPC
     * 
     * @var array
     */
    protected $config = array(
        'encoding' => 'utf-8',
        'escaping' => 'markup',
        'verbosity' => 'no_white_space'
    );

    /**
     * User's MD5 hash of the password
     * 
     * @var array
     */
    protected $passwordHash;

    /**
     * Post url
     * 
     * @var string
     */
    protected $url;

    /**
     * Post authentication number
     * 
     * @var string
     */
    protected $anum;

    /**
     * The last response from the Livejournal.com
     * 
     * @var array
     */
    protected $response;

    /**
     * Flag which activates \r and \n removing from the post's body
     * 
     * @var boolean
     */
    protected $deleteNewLinesFlag = false;

    /**
     * Constructor
     * 
     * @param string  $username
     *                Username for the Livejournal.com
     * @param string  $password
     *                Password or MD5 hash of the password for the Livejournal.com
     * @param boolean $isHash
     *                Was $password alreary hashed?
     */
    public function __construct($username, $password, $isHash = false)
    {
        $this->data['username'] = $username;
        $this->passwordHash = $isHash ? $password : md5($password);
        
        $this->setTime(time());
    }

    /**
     * Setter for entry's date and time
     * 
     * @param integer $timestamp
     *            Timestamp with entry's date and time
     */
    public function setTime($timestamp)
    {
        $this->data['year'] = (int) date("Y", $timestamp);
        $this->data['mon'] = (int) date("m", $timestamp);
        $this->data['day'] = (int) date("d", $timestamp);
        $this->data['hour'] = (int) date("G", $timestamp);
        $this->data['min'] = (int) date("i", $timestamp);
    }

    /**
     * Setter for entry's tags
     * 
     * @param array $tagList
     *            List of entry's tags
     */
    public function setTags($tagList)
    {
        $this->data['props']['taglist'] = implode(',', $tagList);
    }

    /**
     * Method adds tag to the list of entry's tags
     * 
     * @param string $tag
     *            Tag which will be added to the list of entry's tags
     */
    public function addTag($tag)
    {
        if (!empty($this->data['props']['taglist'])) {
            $this->data['props']['taglist'] .= ',' . $tag;
        } else {
            $this->data['props']['taglist'] = $tag;
        }
    }

    /**
     * Method allows to set entry's properties.
     * List of available properties:
     * http://www.livejournal.com/doc/server/ljp.csp.proplist.html
     * 
     * @param string $name            
     * @param mixed $value            
     */
    public function setMetadata($name, $value)
    {
        $this->data['props'][$name] = $value;
    }

    /**
     * Setter for the entry's subject.
     * Subject is optional
     * 
     * @param string $subject
     *            Subject of the entry
     */
    public function setSubject($subject)
    {
        $this->data['subject'] = $subject;
    }

    /**
     * Setter for the entry's body
     * 
     * @param string $body
     *            Body of the entry
     */
    public function setBody($body)
    {
        $this->data['event'] = $body;
        
        if ($this->deleteNewLinesFlag) {
            $this->deleteNewLines();
        }
    }

    /**
     * Method sets the 'private' flag (only for friends)
     * By default: entry will be 'public'
     */
    public function setPrivate()
    {
        $this->data['security'] = 'private';
    }

    /**
     * Method turns on \r and \n removing from the entry's body.
     * Sometimes it's usable because the Livejournal.com translate new lines to
     * the <br>
     */
    public function setDeleteNewLines()
    {
        $this->deleteNewLinesFlag = true;
        $this->deleteNewLines();
    }

    /**
     * Setter of the entry's id
     * 
     * @param integer $id
     *            Entry's id
     */
    public function setId($id)
    {
        $this->data['itemid'] = (int) $id;
    }

    /**
     * Updating or creating of the entry
     * 
     * @return boolean
     */
    public function save()
    {
        if (!$this->challengePrepare()) {
            return false;
        }
        
        if (isset($this->data['itemid'])) {
            $procedure = 'editevent';
        } else {
            $procedure = 'postevent';
        }
        
        $this->request($procedure, $this->data);
        if (xmlrpc_is_fault($this->response) || !isset($this->response['itemid']) ||
            !isset($this->response['url']) || !isset($this->response['anum'])) {
            
            return false;
        } else {
            $this->data['itemid'] = $this->response['itemid'];
            $this->url = $this->response['url'];
            $this->anum = $this->response['anum'];
            
            return true;
        }
    }

    /**
     * Getter of the last error's description
     * 
     * @return string|null Last error's description
     */
    public function getError()
    {
        if (isset($this->response['faultString'])) {
            return $this->response['faultString'];
        } elseif (isset($this->response['errmsg'])) {
            return $this->response['errmsg'];
        }
        return null;
    }

    /**
     * Getter of the last error's code
     * 
     * @return integer|null Last error's code
     */
    public function getErrorCode()
    {
        if (isset($this->response['faultCode'])) {
            return $this->response['faultCode'];
        }
        return null;
    }

    /**
     * Getter of the entry's id
     * 
     * @return integer|null Entry's id
     */
    public function getId()
    {
        if (isset($this->data['itemid'])) {
            return $this->data['itemid'];
        }
        return null;
    }

    /**
     * Getter of the entry's authentication number
     * 
     * @return integer Entry's authentication number
     */
    public function getAnum()
    {
        return $this->anum;
    }

    /**
     * Getter of the entry's url
     * 
     * @return string Entry's url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Method returns context for the POST request to the API
     * 
     * @param string $request
     *            XML-RPC request
     * @return resource
     */
    protected function getContext($request)
    {
        return stream_context_create(
            array(
                'http' => array(
                    'method' => "POST",
                    'header' => "Content-Type: text/xml; charset=UTF-8\r\n" .
                         "User-Agent: Yii livejournal extension\r\n",
                        'content' => $request
                )
            )
        );
    }

    /**
     * Removes \r and \n from the entry's body
     */
    protected function deleteNewLines()
    {
        if (isset($this->data['event'])) {
            $this->data['event'] = str_replace("\r", '', $this->data['event']);
            $this->data['event'] = str_replace("\n", '', $this->data['event']);
        }
    }

    /**
     * Request for the "challenge" which will be used for the "auth_response"
     * hash generating
     * 
     * @return boolean
     */
    protected function challengePrepare()
    {
        $this->request('getchallenge');
        if (xmlrpc_is_fault($this->response)) {
            return false;
        } else {
            $this->data['auth_challenge'] = $this->response['challenge'];
            $this->data['auth_response'] = md5(
                $this->data['auth_challenge'] . $this->passwordHash
            );
            return true;
        }
    }

    /**
     * Method makes XML-RPC request to the API
     * 
     * @param string $procedure
     *               Procedure's name
     * @param array  $data
     *               Request's information
     */
    protected function request($procedure, $data = array())
    {
        $request = xmlrpc_encode_request("LJ.XMLRPC." . $procedure, $data, $this->config);
        $context = $this->getContext($request);
        $file = file_get_contents("http://www.livejournal.com/interface/xmlrpc", false, $context);
        $this->response = xmlrpc_decode($file);
    }
}
