Yii extension for Livejournal.com
=============

With ELivejournal class you can create new entry and update existing entry on Livejournal blog.

Requirements
-------------
- Yii 1.0, 1.1 or above
- Enabled XML-RPC support in PHP

Usage
-------------
#### Simple usage:
```php
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
```

#### Advanced usage:
```php
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
//Sometimes it's usable because the Livejournal.com translates new lines to the <br>
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
```

## Change log
#### yii-livejournal 0.3 (Dec 23, 2011)
- fixed: now extension is compatible with the latest API where User-Agent header is
required

#### yii-livejournal 0.2 (Sep 9, 2011)
- changed: you don't have to store original livejournal.com password because now ELivejournal constructor can accept md5 hash of the password

#### yii-livejournal 0.1.1 (Aug 25, 2011)
- small fixes

## Resources
[yii-livejournal extension page on the yiiframework.com](http://www.yiiframework.com/extension/livejournal)