<?php 
/**
 * MailierQueue.php
 * @author Jason Lee http://www.mr-jason.com
 */
namespace doctorjason\mailerqueue;

use Yii;
use yii\swiftmailer\Mailer;
use yii\swiftmailer\Message;
/**
 * MailerQueue is a sub class of [yii\switmailer\Mailer](https://github.com/yiisoft/yii2-swiftmailer/blob/master/Mailer.php) 
 * which intends to replace it.
 * 
 * Configuration is the same as in `yii\switmailer\Mailer` with some additional properties to control the mail queue
 * 
 * ~~~
 *  'components' => [
 *      ...
 *      'mailerqueue' => [
 *          'class' => 'doctorjason\mailerqueue\MailerQueue',
 *          'db' => '1',
 *          'key' => 'mailer',
 *          'transport' => [
 *              'class' => 'Swift_SmtpTransport',
 *              'host' => 'localhost',
 *              'username' => 'username',
 *              'password' => 'password',
 *              'port' => '587',
 *              'encryption' => 'tls',
 *          ],
 *      ],
 *      ...
 *  ],
 * ~~~
 * 
 * @see http://www.yiiframework.com/doc-2.0/yii-swiftmailer-mailer.html
 * @see http://www.yiiframework.com/doc-2.0/ext-swiftmailer-index.html
 * 
 * This extension replaces `yii\switmailer\Message` with `nterms\mailqueue\Message' 
 * to enable queuing right from the message.
 * 
 */
class MailerQueue extends Mailer
{
    /**
     * @var string message default class name.
     *   
     */
    public $messageClass = 'doctorjason\mailerqueue\Message';

    /**
     * @var string key name
     */
    public $key = 'mails';

    /**
     * @var string the name of the database table to store the mail queue.
     */
    public $db = '0';
    
    /**
     * Initializes the MailerQueue component.
     */
    public function init()
    {
        parent::init();
    }
    
    /**
     * Sends out the messages in email queue.
     * 
     * @return boolean true if all messages are successfully sent out
     */
    public function process()
    {
        $redis = Yii::$app->redis;
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config.');
        }
        if ($redis->select($this->db) && $items = $redis->lrange($this->key, 0, -1)) {
            $message = new Message();
            foreach ($items as $item) {
                $item = json_decode($item, true);
                if (empty($item) || !$this->setMessage($message, $item)) {
                    throw new \ServerErrorHttpException('message object error');
                }
                if (!$message->send($this)) {
                    Yii::$app->log(json_encode($item));
                } else {
                    $redis->lrem($this->key, -1, json_encode($item));
                }
            }
        }
        return true;
    }

    /**
     * 获取message对象
     *
     */
    public function setMessage($message, $data)
    {
        if (empty($message)) {
            throw new \ServerErrorHttpException('message object error');
        }
        if( !empty($data['from']) && !empty($data['to'])) {
            $message->setFrom($data['from'])->setTo($data['to']);
            if(!empty($data['cc'])) {
                $message->setCc($data['cc']);
            }
            if(!empty($data['bcc'])) {
                $message->setBcc($data['bcc']);
            }
            if(!empty($data['reply_to'])) {
                $message->setReplyTo($data['reply_to']);
            }
            if(!empty($data['charset'])) {
                $message->setCharset($data['charset']);
            }
            $message->setSubject($data['subject']);
            if(!empty($data['html_body'])) {
                $message->setHtmlBody($data['html_body']);
            }
            if(!empty($data['text_body'])) {
                $message->setTextBody($data['text_body']);
            }
            return $message;
        }
        return false;
    }



}
