<?php

namespace Chatify;

use Chatify\Http\Models\Message;
use Chatify\Http\Models\Favorite;
use Pusher\Pusher;
use Illuminate\Support\Facades\Auth;
use Exception;
use DB;

class ChatifyMessenger
{
    /**
     * Allowed extensions to upload attachment
     * [Images / Files]
     *
     * @var
     */
    public static $allowed_images = array('PNG','JPG','png','jpg','jpeg','gif');
    public static $allowed_files  = array('zip','rar','txt','pdf','doc','docx','ppt','pptx','xls','xlsx');

    /**
     * This method returns the allowed image extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedImages(){
        return self::$allowed_images;
    }

    /**
     * This method returns the allowed file extensions
     * to attach with the message.
     *
     * @return array
     */
    public function getAllowedFiles(){
        return self::$allowed_files;
    }

    /**
     * Returns an array contains messenger's colors
     *
     * @return array
     */
    public function getMessengerColors(){
        return [
            '1' => '#2180f3',
            '2' => '#2196F3',
            '3' => '#00BCD4',
            '4' => '#3F51B5',
            '5' => '#673AB7',
            '6' => '#4CAF50',
            '7' => '#FFC107',
            '8' => '#FF9800',
            '9' => '#ff2522',
            '10' => '#9C27B0',
        ];
    }

    /**
     * Pusher connection
     */
    public function pusher()
    {
        return new Pusher(
            config('chatify.pusher.key'),
            config('chatify.pusher.secret'),
            config('chatify.pusher.app_id'),
            [
                'cluster' => config('chatify.pusher.options.cluster'),
                'useTLS' => config('chatify.pusher.options.useTLS')
            ]
        );
    }

    /**
     * Trigger an event using Pusher
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     * @return void
     */
    public function push($channel, $event, $data)
    {
        return $this->pusher()->trigger($channel, $event, $data);
    }

    /**
     * Authintication for pusher
     *
     * @param string $channelName
     * @param string $socket_id
     * @param array $data
     * @return void
     */
    public function pusherAuth($channelName, $socket_id, $data = []){
        return $this->pusher()->socket_auth($channelName, $socket_id, $data);
    }

    /**
     * Fetching all the message     
     *
     * @param int $id
     * @param string $param
     * @return array
     */
    public function fetchMessage($param){
        $explodedParam = explode('-', $param);
        $id = $explodedParam[1];
        $type = $explodedParam[0];

        $attachment = $attachment_type = $attachment_title = null;

        $msg = DB::table('messages')
        ->select('*')
        ->where('id', $id)
        ->whereIn('type', [$type])
        ->first();

        $msgCount = DB::table('messages')
        ->select('*')
        ->where('id', $id)
        ->whereIn('type', [$type])
        ->count();
        $memberID = null;
        $memberName = null;

        if($msgCount != 0){
                //try{
                    $explodedMessage = explode('-', $msg->body);
                    /*}catch(Exeption $e){
                        dd($msg);
                    }*/    
                    if(count($explodedMessage) == 6){
                        if($explodedMessage[3] == 'update'){
                            if($explodedMessage[0] != null){
                                $selectTheAddedMember = DB::table('users')->where('id', $explodedMessage[0])
                                ->get()
                                ->first();
                                $memberID = $explodedMessage[0];
                                $memberName = $selectTheAddedMember->id == auth()->user()->id ? "You" : $selectTheAddedMember->first_name.' '.$selectTheAddedMember->last_name;
                            }else{
                                $memberID = null;
                                $memberName = $explodedMessage[0];
                            }
                        }
                        if($explodedMessage[3] == 'added'){
                            if($explodedMessage[0] != null){
                                $selectTheAddedMember = DB::table('users')->where('id', $explodedMessage[0])
                                ->get()
                                ->first();
                                $memberID = $explodedMessage[0];
                                $memberName = $selectTheAddedMember->id == auth()->user()->id ? "You" : $selectTheAddedMember->first_name.' '.$selectTheAddedMember->last_name;
                            }else{
                                $memberID = null;
                                $memberName = $explodedMessage[0];
                            }
                        }
                        if($explodedMessage[3] == 'removed'){
                            if($explodedMessage[0] != null){
                                $selectTheAddedMember = DB::table('users')->where('id', $explodedMessage[0])
                                ->get()
                                ->first();
                                $memberID = $explodedMessage[0];
                                $memberName = $selectTheAddedMember->id == auth()->user()->id ? "You" : $selectTheAddedMember->first_name.' '.$selectTheAddedMember->last_name;
                            }else{
                                $memberID = null;
                                $memberName = $explodedMessage[0];
                            }
                        }    
                    }    
                //dd($msg->attachment);
                // If message has attachment
                if(isset($msg->attachment)){
                    // Get attachment and attachment title
                    $att = explode(',',$msg->attachment);
                    $attachment       = $att[0];
                    $attachment_title = $att[1];
        
                    // determine the type of the attachment
                    $ext = pathinfo($attachment, PATHINFO_EXTENSION);
                    $attachment_type = in_array($ext,$this->getAllowedImages()) ? 'image' : 'file';
                }
        
                $selectTheUserInfo = DB::table('users')
                ->select('*')
                ->where('id', $msg->from_id)
                ->get()
                ->first();
                
                $formattedDate = \Carbon\Carbon::parse($msg->created_at);
                $formattedTime = \Carbon\Carbon::parse($msg->created_at)->setTimezone('Asia/Manila');
                if($formattedDate->isToday()){
                    $Date = 'Today at '.$formattedTime->format('g:i:s a');
                }elseif($formattedDate->isYesterday()){
                    $Date = 'Yesterday at '.$formattedTime->format('g:i:s a');
                }else{
                    $Date = \Carbon\Carbon::parse($msg->created_at)->format('l F d, yy').' at '.$formattedTime->format('g:i:s a');
                }
        
                return [
                    'id' => $msg->id,
                    //'first_date' => $firstMessageIndicator->created_at,
                    'from_id_name' => $selectTheUserInfo->first_name.' '.$selectTheUserInfo->last_name,
                    'from_id' => $msg->from_id,
                    'to_id' => $msg->to_id,
                    'message' => $msg->body,
                    'attachment' => [$attachment, $attachment_title, $attachment_type],
                    'moment' => $formattedTime->diffForHumans(),
                    'date' => \Carbon\Carbon::parse($msg->created_at)->format('l F,dy'),
                    'time' => $Date,
                    'fullTime' => $msg->created_at,
                    'viewType' => $msg->from_id == Auth::user()->id ? 'sender' : 'default',
                    'seen' => $msg->seen,
                    'member_id' => $memberID,
                    'member_name' => $memberName
                ];
        
        }
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param int $messenger_id
     * @param Collection $user
     * @return void
     */
    public function getContactItemMobile($messenger_id, $user, $type){
        
        // get last message
        $lastMessage = self::getLastMessageQuery($user->id, $type);
        // Get Unseen messages counter
        $unseenCounter = self::countUnseenMessages($user->id, $type);
        
        if($type == 'user'){
            //dd($lastMessage->from_id);
            $getTheUserInfo = DB::table('users')
            ->select('*')
            ->where('id', $user->id)
            ->get()
            ->first();

            return [
                'get' => $type,
                'user' => $user,
                'from_name' => $getTheUserInfo->first_name.' '.$getTheUserInfo->last_name,
                'lastMessage' => $lastMessage,
                'unseenCounter' => $unseenCounter,
                'type'=> $type,
                'id' => $messenger_id,
            ];

        }else{
            //dd($lastMessage->id);
            $getTheUserInfo = DB::table('chat_groups')
            ->select('*')
            ->where('id', $user->id)
            ->get()
            ->first();

            $explodedMessage = explode('-', $lastMessage->body);

            $getNewMemberInfo = DB::table('users')
            ->select('*')
            ->where('id', $explodedMessage[0])
            ->get()
            ->first();

            $getNewMemberInfoCount = DB::table('users')
            ->select('*')
            ->where('id', $explodedMessage[0])
            ->count();

            $lastID = end($explodedMessage);

            $getTheLeaverInfo = DB::table('users')
            ->select('*')
            ->where('id', $lastID)
            ->get()
            ->first();

            $getTheLeaverInfoCount = DB::table('users')
            ->select('*')
            ->where('id', $lastID)
            ->count();


            return  [
                'get' => $type,
                'user' => $getTheUserInfo,
                'from_user_name' => $getTheLeaverInfoCount != 0 ? $getTheLeaverInfo->first_name.' '.$getTheLeaverInfo->last_name : null,
                'avatar' => $getTheUserInfo->avatar,
                'member_name' => $getNewMemberInfoCount != 0 ? $getNewMemberInfo->first_name.' '.$getNewMemberInfo->last_name : null,
                'user_id' => $explodedMessage[0],
                'from_name' => $getTheUserInfo->group_chat_name,
                'lastMessage' => $lastMessage,
                'unseenCounter' => $unseenCounter,
                'type'=> $type,
                'id' => $messenger_id,
            ];

        }
        

    }

    /**
     * Return a message card with the given data.
     *
     * @param array $data
     * @param string $viewType
     * @return void
     */
    public function messageCard($data, $viewType = null){
        $data['viewType'] = ($viewType) ? $viewType : $data['viewType'];
        return view('Chatify::layouts.messageCard',$data)->render();
    }

    /**
     * Default fetch messages query between a Sender and Receiver.
     *
     * @param string $type
     * @param int $user_id
     * @return Collection
     */
    public function fetchMessagesQuery($user_id, $type){

        if($type == 'group'){
            return Message::where('type', $type)
            ->where('to_id', $user_id);
        }else{
            return Message::whereIn('type', [$type])->where('from_id',Auth::user()->id)->where('to_id',$user_id)
                    ->orWhere('from_id',$user_id)->where('to_id',Auth::user()->id);
        }
        
    }

    /**
     * create a new message to database
     *
     * @param array $data
     * @return void
     */
    public function newMessage($data){
        $message = new Message();
        $message->id = $data['id'];
        $message->type = $data['type'];
        $message->from_id = $data['from_id'];
        $message->to_id = $data['to_id'];
        $message->body = $data['body'];
        $message->attachment = $data['attachment'];
        $message->save();
    }

    /**
     * Make messages between the sender [Auth user] and
     * the receiver [User id] as seen.
     *
     * @param int $user_id
     * @return bool
     */
    public function makeSeen($user_id, $type){
        Message::where('type', $type)
                ->where('from_id',$user_id)
                ->where('to_id',Auth::user()->id)
                ->where('seen', 0)
                ->update(['seen' => 1]);
        return 1;
    }

    /**
     * Get last message for a specific user
     *
     * @param int $user_id
     * @return Collection
     */
    public function getLastMessageQuery($user_id, $type){
        return self::fetchMessagesQuery($user_id, $type)->orderBy('created_at','DESC')->latest()->first();
    }

    /**
     * Count Unseen messages
     *
     * @param int $user_id
     * @return Collection
     */
    public function countUnseenMessages($user_id, $type){
        return Message::where('type', $type)->where('from_id',$user_id)->where('to_id',Auth::user()->id)->where('seen',0)->count();
    }

    /**
     * Get user list's item data [Contact Itme]
     * (e.g. User data, Last message, Unseen Counter...)
     *
     * @param int $messenger_id
     * @param Collection $user
     * @return void
     */
    public function getContactItem($messenger_id, $user, $type){
        
        // get last message
        $lastMessage = self::getLastMessageQuery($user->id, $type);
        // Get Unseen messages counter
        $unseenCounter = self::countUnseenMessages($user->id, $type);
        
        if($type == 'user'){
            //dd($lastMessage->from_id);
            $getTheUserInfo = DB::table('users')
            ->select('*')
            ->where('id', $user->id)
            ->get()
            ->first();

            return view('Chatify::layouts.listItem', [
                'get' => $type,
                'user' => $user,
                'from_name' => $getTheUserInfo->first_name.' '.$getTheUserInfo->last_name,
                'lastMessage' => $lastMessage,
                'unseenCounter' => $unseenCounter,
                'type'=> $type,
                'id' => $messenger_id,
            ])->render();

        }else{
            //dd($lastMessage->id);
            $getTheUserInfo = DB::table('chat_groups')
            ->select('*')
            ->where('id', $user->id)
            ->get()
            ->first();

            $explodedMessage = explode('-', $lastMessage->body);

            $getNewMemberInfo = DB::table('users')
            ->select('*')
            ->where('id', $explodedMessage[0])
            ->get()
            ->first();

            $getNewMemberInfoCount = DB::table('users')
            ->select('*')
            ->where('id', $explodedMessage[0])
            ->count();

            $lastID = end($explodedMessage);

            $getTheLeaverInfo = DB::table('users')
            ->select('*')
            ->where('id', $lastID)
            ->get()
            ->first();

            $getTheLeaverInfoCount = DB::table('users')
            ->select('*')
            ->where('id', $lastID)
            ->count();


            return view('Chatify::layouts.listItem', [
                'get' => $type,
                'user' => $getTheUserInfo,
                'from_user_name' => $getTheLeaverInfoCount != 0 ? $getTheLeaverInfo->first_name.' '.$getTheLeaverInfo->last_name : null,
                'avatar' => $getTheUserInfo->avatar,
                'member_name' => $getNewMemberInfoCount != 0 ? $getNewMemberInfo->first_name.' '.$getNewMemberInfo->last_name : null,
                'user_id' => $explodedMessage[0],
                'from_name' => $getTheUserInfo->group_chat_name,
                'lastMessage' => $lastMessage,
                'unseenCounter' => $unseenCounter,
                'type'=> $type,
                'id' => $messenger_id,
            ])->render();

        }
        

    }

    /**
     * Check if a user in the favorite list
     *
     * @param int $user_id
     * @return boolean
     */
    public function inFavorite($user_id){
        return Favorite::where('user_id', Auth::user()->id)
                        ->where('favorite_id', $user_id)->count() > 0
                        ? true : false;

    }

    /**
     * Make user in favorite list
     *
     * @param int $user_id
     * @param int $star
     * @return boolean
     */
    public function makeInFavorite($user_id, $action){
        if ($action > 0) {
            // Star
            $star = new Favorite();
            $star->id = rand(9,99999999);
            $star->user_id = Auth::user()->id;
            $star->favorite_id = $user_id;
            $star->save();
            return $star ? true : false;
        }else{
            // UnStar
            $star = Favorite::where('user_id',Auth::user()->id)->where('favorite_id',$user_id)->delete();
            return $star ? true : false;
        }
    }

    /**
     * Get shared photos of the conversation
     *
     * @param int $user_id
     * @return array
     */
    public function getSharedPhotos($user_id, $type){
        $images = array(); // Default
        // Get messages
        $msgs = $this->fetchMessagesQuery($user_id, $type)->orderBy('created_at','DESC');
        if($msgs->count() > 0){
            foreach ($msgs->get() as $msg) {
                // If message has attachment
                if($msg->attachment){
                    $attachment = explode(',',$msg->attachment)[0]; // Attachment
                    // determine the type of the attachment
                    in_array(pathinfo($attachment, PATHINFO_EXTENSION), $this->getAllowedImages())
                    ? array_push($images, $attachment) : '';
                }
            }
        }
        return $images;

    }

    /**
     * Delete Conversation
     *
     * @param int $user_id
     * @return boolean
     */
    public function deleteConversation($user_id, $type){
        try {
            foreach ($this->fetchMessagesQuery($user_id, $type)->get() as $msg) {
                // delete from database
                $msg->delete();
                // delete file attached if exist
                if ($msg->attachment) {
                    $path = storage_path('app/public/'.config('chatify.attachments.folder').'/'.explode(',', $msg->attachment)[0]);
                    if(file_exists($path)){
                        @unlink($path);
                    }
                }
            }
            return 1;
        }catch(Exception $e) {
            return 0;
        }
    }

}
