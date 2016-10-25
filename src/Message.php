<?php
/**
 * Message.php
 * @author JasonLee http://www.mr-jason.com
 */
namespace doctorjason\mailerqueue;
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
        $item['from'] = !empty($this->from) ? array_keys((array)$this->from) : [];
        $item['to'] = !empty($this->getTo()) ? array_keys((array)$this->getTo()) : [];
        $item['cc'] = !empty($this->getCc()) ? array_keys((array)$this->getCc()) : [];
        $item['bcc'] = !empty($this->getBcc()) ? array_keys((array)$this->getBcc()) : [];
        $item['reply_to'] = !empty($this->getReplyTo()) ? array_keys((array)$this->getReplyTo()) : [];
        $item['charset'] = !empty($this->getCharset()) ? $this->getCharset() : 'utf-8';
        $item['subject'] = !empty($this->getSubject()) ? $this->getSubject() : '';
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
