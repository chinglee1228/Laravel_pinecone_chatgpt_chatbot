<?php

namespace App\Http\Controllers;



use App\Http\Controllers\Controller;

use App\Helpers\ServerEvent;
use App\Models\Chat;
use App\Models\Message;
use App\Service\QueryEmbedding;
use App\Service\PineconeService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class ChatbotController extends Controller
{
    //
    private $openAiService;
    protected $query;

    protected  $pinecone;

    public function __construct(QueryEmbedding $query , PineconeService $pinecone)
    {
        $this->query = $query;
        $this->pinecone = $pinecone;

    }

    public function index(){

        return view("Page/Chatbot/chatbot");
    }
    public function show()
    {
        //$chat_id = '1';

        //$chat = Chat::where('id',$chat_id)->first();
        return view('Page/Chatbot/chatbot', [
            //'chat' => $chat,
            //'messages' => Message::query()->where('chat_id', $chat->id)->get()
        ]);
    }


    public function chat(Request $request)
    {
        try {
            $question = $request->question; // 接收到的訊息
            // 送到AI模型處理, 假设 sendToAIModel 方法会返回 AI 的回答
            $response = $this->sendToAIModel($question);
            // 保存用户的问题和 AI 的回答
            return response()->json([
                //'question' => $question,
                //'answer' => $response,
                //'status' =>'processing',
            ]);

        } catch (Exception $e) {
            Log::error($e);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendToAIModel(Request $request)
    {
        return response()->stream(function () use ($request) {
            try{
                $question = $request->question;

                //$context = '這個人是一個男生，我今年20歲，身高180，是台北人，還在是學生';//測試當作查詢到的資料
                //$queryVectors = $this->query->getQueryEmbedding($question);//將問題轉換成向量
                //$queryPinecone= $this->queryPinecone->queryPinecone($queryVectors);//查詢pinecone
                $context = $this->pinecone->GetRelevantContent($question)?? [];//提取text
                $stream = $this->query->askQuestionStreamed($context, $question);//使用查詢到的資料進行問答

                foreach ($stream as $response) {
                    $message = $response->choices[0]->delta->content;
                    //return $message;
                    //檢查連接是否中斷
                    if (connection_aborted()) {
                        break;
                        }
                    ServerEvent::send($message, "");//丟給前端
                    }

                } catch (Exception $e) {
                    Log::error($e);
                    ServerEvent::send("客服機器人異常，請洽詢客服專員!");
                    }
                }, 200, [
                    'Cache-Control' => 'no-cache',
                    'Connection' => 'keep-alive',
                    'X-Accel-Buffering' => 'no',
                    'Content-Type' => 'text/event-stream',
                ]);
            }
        }
