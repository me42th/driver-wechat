<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\Drivers\WeChat\WeChatAudioDriver;
use BotMan\BotMan\Messages\Attachments\Audio;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WeChatAudioDriverTest extends PHPUnit_Framework_TestCase
{
    /**
     * Valid WeChat audio XML.
     * @var string
     */
    protected $validXml;

    /**
     * Invalid WeChat audio XML.
     * @var string
     */
    protected $invalidXml;

    public function setUp()
    {
        parent::setUp();

        $this->validXml = '<xml><ToUserName><![CDATA[to_user_name]]></ToUserName>
            <FromUserName><![CDATA[from_user_name]]></FromUserName>
            <CreateTime>1483534197</CreateTime>
            <MsgType><![CDATA[voice]]></MsgType>
            <Content><![CDATA[foo]]></Content>
            <MsgId>1234567890</MsgId>
            <MediaId>12345</MediaId>
            </xml>';

        $this->invalidXml = '<xml><ToUserName><![CDATA[to_user_name]]></ToUserName>
            <FromUserName><![CDATA[from_user_name]]></FromUserName>
            <CreateTime>1483534197</CreateTime>
            <MsgType><![CDATA[photo]]></MsgType>
            <Content><![CDATA[foo]]></Content>
            <MsgId>1234567890</MsgId>
            </xml>';
    }

    public function tearDown()
    {
        m::close();
    }

    private function getDriver($xmlData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($xmlData);
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        return new WeChatAudioDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('WeChatAudio', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver('foo');
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver($this->validXml);
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_voice_pattern()
    {
        $html = m::mock(Curl::class);
        $html->shouldReceive('post')->once()->with('https://api.wechat.com/cgi-bin/token?grant_type=client_credential&appid=WECHAT-APP-ID&secret=WECHAT-APP-KEY',
                [], [])->andReturn(new Response(json_encode([
                'access_token' => 'SECRET_TOKEN',
            ])));

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($this->validXml);

        $driver = new WeChatAudioDriver($request, [
            'wechat' => [
                'app_id' => 'WECHAT-APP-ID',
                'app_key' => 'WECHAT-APP-KEY',
            ],
        ], $html);

        $messages = $driver->getMessages();
        $this->assertTrue(is_array($messages));
        $this->assertEquals('%%%_AUDIO_%%%', $messages[0]->getText());
    }

    /** @test */
    public function it_returns_the_voice()
    {
        $html = m::mock(Curl::class);
        $html->shouldReceive('post')->once()->with('https://api.wechat.com/cgi-bin/token?grant_type=client_credential&appid=WECHAT-APP-ID&secret=WECHAT-APP-KEY',
                [], [])->andReturn(new Response(json_encode([
                'access_token' => 'SECRET_TOKEN',
            ])));

        $request = m::mock(\Symfony\Component\HttpFoundation\Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn($this->validXml);

        $driver = new WeChatAudioDriver($request, [
            'wechat' => [
                'app_id' => 'WECHAT-APP-ID',
                'app_key' => 'WECHAT-APP-KEY',
            ],
        ], $html);

        $message = $driver->getMessages()[0];
        $this->assertSame(Audio::PATTERN, $message->getText());
        $this->assertSame('http://file.api.wechat.com/cgi-bin/media/get?access_token=SECRET_TOKEN&media_id=12345',
            $message->getAudio()[0]->getUrl());
        $this->assertSame($message->getPayload(), $message->getAudio()[0]->getPayload());
    }
}
