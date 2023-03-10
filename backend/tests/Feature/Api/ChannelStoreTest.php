<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Channel;

class ChannelStoreTest extends TestCase
{
    // このトレイトはテスト実行中にトランザクション貼ってくれ、
    // テスト後にロールバックしてくれるものです。テスト後もキレイなDBを保ってくれます。
    use RefreshDatabase;
    
    /**
     * @test
     */
    public function channelsの登録APIの正常系を確認する()
    {
        // APIを実行するユーザーを作成
        $user = \App\Models\User::factory()->create();
        // 送信データを定義
        $postData = [
            'name' => 'チャンネル名です',
        ];

        // API実行
        $response = $this->actingAs($user)->json(
            'POST',
            route('api.channels.store'),
            $postData
        );

        // レスポンスをアサート
        $response->assertStatus(201)->assertJson([
            // サーバサイドで振られるIDやUUIDは確認しようがないため除外
            'name' => $postData['name'],
            "joined" => true,
        ]);

        // DBアサート
        // channelsテーブルにデータ登録ができていることを確認
        $this->assertDatabaseHas('channels', [
            'id' => $response['id'],
            'name' => $postData['name'],
        ]);
        // channel_userテーブルに紐づきができている（チャンネルに参加状態になっている）ことを確認
        $this->assertDatabaseHas('channel_user', [
            'channel_id' => $response['id'],
            'user_id' => $user->id,
        ]);
    }

        /**
     * @test
     */
    public function channelsの登録APIの異常系_DBへのインサートでエラーになった場合は500エラー()
    {
        $user = \App\Models\User::factory()->create();
        $postData = [
            'name' => 'チャンネル名です',
        ];
        // Channelクラスをモックして、addFirstMember()の挙動を差し替え
        $this->partialMock(Channel::class, function ($mock) {
            $mock
                ->shouldReceive('addFirstMember')
                ->once() // １回だけ呼ばれること
                ->withAnyArgs() // 今回はエラーパターンのため、引数は何でもOK（チェックはしない）
                ->andThrow(new \Exception()); // Exceptionをthrowするように変更
            $mock // Model側で呼ばれているnewInstanceメソッドは新たなインスタンスを返して動くように
                ->shouldReceive('newInstance')
                ->once()
                ->andReturn(new Channel());
        });

        // API実行
        $response = $this->actingAs($user)->json(
            'POST',
            route('api.channels.store'),
            $postData
        );

        // レスポンスをアサート
        $response->assertStatus(500);

        // DBアサート
        // channelsテーブルにデータ登録されていないことを確認
        $this->assertDatabaseMissing('channels', [
            'name' => $postData['name'],
        ]);
        // channel_userテーブルに紐づきができていないことを確認
        $this->assertDatabaseMissing('channel_user', [
            'user_id' => $user->id,
        ]);
    }
}

