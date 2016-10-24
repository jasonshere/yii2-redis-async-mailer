<?php
/**
 * Message.php
 * @author JasonLee http://www.mr-jason.com
 */
namespace jason\mailerqueue;
use Yii;

/**
 * Extends `yii\swiftmailer\Message` to enable queuing.
 *
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-message.html
 */
class Message extends \yii\swiftmailer\Message
{

    /**
     * Enqueue the message storing it in database.
     *
     * @return boolean true on success, false otherwise
     */
    public function queue()
    {
        $redis = Yii::$app->redis;
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config.');
        }
        $obj = Yii::$app->mailer;
        if (empty($obj) || !$redis->select($obj->db)) {
            throw new \yii\base\InvalidConfigException('redis connected failed.');
        }
        $item = [];
        $item['from'] = array_keys($this->from);
        $item['to'] = array_keys($this->getTo());
        $item['cc'] = array_keys($this->getCc());
        $item['bcc'] = array_keys($this->getBcc());
        $item['reply_to'] = array_keys($this->getReplyTo());
        $item['charset'] = $this->getCharset();
        $item['subject'] = $this->getSubject();
        $parts = $this->getSwiftMessage()->getChildren();
        // if message has no parts, use message
        if ( !is_array($parts) || !sizeof($parts) ) {
            $parts = [ $this->getSwiftMessage() ];
        }
        foreach( $parts as $part ) {
            if( !$part instanceof \Swift_Mime_Attachment ) {
                /* @var $part \Swift_Mime_MimeEntity */
                switch( $part->getContentType() ) {
                    case 'text/html':
                        $item['html_body'] = $part->getBody();
                        break;
                    case 'text/plain':
                        $item['text_body'] = $part->getBody();
                        break;
                }
                if( !$item['charset'] ) {
                    $item['charset'] = $part->getCharset();
                }
            }
        }
        return Yii::$app->redis->rpush($obj->key, json_encode($item));
    }

}
