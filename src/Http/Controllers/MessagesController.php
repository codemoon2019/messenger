<?php

namespace Chatify\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Chatify\Http\Models\Message;
use Chatify\Http\Models\Favorite;
use Chatify\Facades\ChatifyMessenger as Chatify;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use DB;
use File;
use Storage;

class MessagesController extends Controller
{
    /**
     * Authinticate the connection for pusher
     *
     * @param Request $request
     * @return void
     */
    public function pusherAuth(Request $request){
        $authData = json_encode([
            'user_id' => Auth::user()->id,
            'user_info' => [
                'name' => Auth::user()->first_name.' '.Auth::user()->last_name
            ]
        ]);
        if (Auth::check()) {
            return Chatify::pusherAuth(
                $request['channel_name'],
                $request['socket_id'],
                $authData
            );
        }
        return new Response('Unauthorized', 401);
    }

    /**
     * Returning the view of the app with the required data.
     *
     * @param int $id
     * @return void
     */
    public function index($id = null)
    {

        $route = (in_array(\Request::route()->getName(), ['user', config('chatify.path')]))
            ? 'user'
            : \Request::route()->getName();
        return view('Chatify::pages.app', [
            'id' => ($id == null) ? 0 : $route . '_' . $id,
            'route' => $route,
            'messengerColor' => Auth::user()->messenger_color,
            'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
        ]);
    }


    /**
     * Fetch data by id for (user/group)
     *
     * @param Request $request
     * @return collection
     */
    public function idFetchData(Request $request)
    {
        // Favorite
        $favorite = Chatify::inFavorite($request['id']);

        if($request['type'] == 'group'){  
            $fetch = DB::table('chat_groups')
            ->select('*')
            ->where('id', $request['id'])
            ->get()
            ->first();

            // send the response
            return Response::json([
                'favorite' => $favorite,
                'fetch' => $fetch,
                'user_avatar' => $fetch->avatar,
                'user_name' => $fetch->group_chat_name,
            ]);
        }
        // User data
        if ($request['type'] == 'user') {
            $fetch = User::where('id', $request['id'])->first();
            
            // send the response
            return Response::json([
                'favorite' => $favorite,
                'fetch' => $fetch,
                'user_avatar' => $fetch->image,
                'user_name' => $fetch->first_name.' '.$fetch->last_name,
            ]);
        }

    }

    /**
     * This method to make a links for the attachments
     * to be downloadable.
     *
     * @param string $fileName
     * @return void
     */
    public function download($fileName)
    {
        $path = 'storage/public/attachments/'.$fileName;
        if (file_exists($path)) {
            return Response::download($path, $fileName);
        } else {
            return abort(404, "Sorry, File does not exist in our server or may have been deleted!");
        }
    }

    /**
     * Send a message to database
     *
     * @param Request $request
     * @return JSON response
     */
    public function send(Request $request)
    {
        // default variables
        $error_msg = $attachment = $attachment_title = null;

        // if there is attachment [file]
        if ($request->hasFile('file')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();
            $allowed_files  = Chatify::getAllowedFiles();
            $allowed        = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');
            // if size less than 150MB
            if ($file->getSize() < 150000000){
                if (in_array($file->getClientOriginalExtension(), $allowed)) {
                    // get attachment name
                    $attachment_title = $file->getClientOriginalName();
                    // upload attachment and store the new name
                    $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
                    $file->move(public_path('app/public/attachment/ticket'), $attachment);
                } else {
                    $error_msg = "File extension not allowed!";
                }
            } else {
                $error_msg = "File size is too long!";
            }
        }

        if (!$error_msg) {
            // send to database
            $messageID = mt_rand(9, 999999999) + time();
            
            Chatify::newMessage([
                'id' => $messageID,
                'type' => $request['type'],
                'from_id' => Auth::user()->id,
                'to_id' => $request['id'],
                'body' => trim(htmlentities($request['message'])),
                'attachment' => ($attachment) ? $attachment . ',' . $attachment_title : null,
            ]);
            
            $newID  = $request['type']."-".$messageID;

            //try{
                // fetch message to send it with the response
                $messageData = Chatify::fetchMessage($newID);
            /*}catch(Exeption $e){
                return $e;
            }*/
            
            // send to user using pusher
            Chatify::push('private-chatify', 'messaging', [
                'type' => $request['type'],
                'from_id' => Auth::user()->id,
                'to_id' => $request['id'],
                'message' => Chatify::messageCard($messageData, 'default')
            ]);
            
        }

        // send the response
        return Response::json([
            'status' => '200',
            'error' => $error_msg ? 1 : 0,
            'error_msg' => $error_msg,
            'message' => Chatify::messageCard(@$messageData),
            'tempID' => $request['temporaryMsgId'],
            'sample' => '200',
            'type' => $request['type'],
        ], 200);

    }

    /**
     * fetch [user/group] messages from database
     *
     * @param Request $request
     * @return JSON response
     */
    public function fetch(Request $request){
        $allMessages = null;
        $query = Chatify::fetchMessagesQuery($request['id'], $request['type'])->orderBy('created_at', 'asc');
        $messages = $query->get();
        if ($query->count() > 0) {
            foreach ($messages as $message) {
                //try{
                    $newID = $request['type'].'-'.$message->id;
                    $allMessages .= Chatify::messageCard(
                        Chatify::fetchMessage($newID)
                    );
                /*}catch(Exception $e){
                    dd($message);
                }*/
            }
            //dd($allMessages);
            return Response::json([
                'count' => $query->count(),
                'messages' => $allMessages,
            ]);
        }
        return Response::json([
            'count' => $query->count(),
            'messages' => '<p class="message-hint" style="margin-top:10px;"><span>Say \'hi\' and start messaging</span></p>',
        ]);
    }

    /**
     * Make messages as seen
     *
     * @param Request $request
     * @return void
     */
    public function seen(Request $request){
        $seen = Chatify::makeSeen($request['id'], $request['type']);
        return Response::json([
            'status' => $seen,
        ], 200);
    }

    /**
     * Get contacts list
     *
     * @param Request $request
     * @return JSON response
     */
    public function getContacts(Request $request){
        
        $Type = $request['type'];

        if($Type == 'users'){
            $Type = 'user';
        }else{
            $Type = 'group';
        }

        //dd($Type);

        if($Type == "user"){
            $users = Message::join('users',  function ($join) {
                $join->on('messages.from_id', '=', 'users.id')
                    ->orOn('messages.to_id', '=', 'users.id');
                })
                ->where('messages.type', $Type)
                ->where('messages.from_id', Auth::user()->id)
                ->orWhere('messages.to_id', Auth::user()->id)
                ->orderBy('messages.created_at', 'desc')
                ->get()
                ->unique('id');   
        }else{
            $dataArray = array();
            $selectAlltheGC = DB::table('chat_group_members')
            ->select('*')
            ->where('uid', Auth::user()->id)
            ->get();
            foreach($selectAlltheGC as $GC){
                $dataArray = Arr::prepend($dataArray, [$GC->group_chat_id]);
            }
            $users = Message::join('chat_groups',  function ($join) {
                $join->on('messages.to_id', '=', 'chat_groups.id');
                })
                ->where('messages.type', $Type)
                ->whereIn('messages.to_id', $dataArray)
                ->orderBy('messages.created_at', 'desc')
                ->get()
                ->unique('id');
        }
    
        if ($users->count() > 0) {
            $contacts = null;
            foreach ($users as $user) {
                if ($user->id != Auth::user()->id) {
                    if($Type == 'user'){
                        $userCollection = User::where('id', $user->id)->first();
                        $contacts .= Chatify::getContactItem($request['messenger_id'], $userCollection, $Type);
                    }else{
                        $userCollection = DB::table('chat_groups')
                        ->where('id', $user->id)
                        ->get()
                        ->first();
                        //$userCollection = User::where('id', $user->id)->first();
                        $contacts .= Chatify::getContactItem($request['messenger_id'], $userCollection, $Type);
                        //dd($contacts);
                    }
                }
            }
        }

        //dd($contacts);
        return Response::json([
            'contacts' => $users->count() > 0 ? $contacts : '<br><p class="message-hint"><span>Your contact list is empty</span></p>',
        ], 200);

    }

    /**
     * Update user's list item data
     *
     * @param Request $request
     * @return JSON response
     */
    public function updateContactItem(Request $request){

        $userCollection = User::where('id', $request['user_id'])->first();
        $contactItem = Chatify::getContactItem($request['messenger_id'], $userCollection, $request['type']);
        return Response::json([
            'contactItem' => $contactItem,
        ], 200);

    }

    /**
     * Search users
     *
     * @param Request $request
     * @return JSON response
     */
    public function selectUsers(Request $request){

        $search = $request->input('search');
        $groupChat = $request->input('group_chat');
        $dataArrayForExistingMembers = array();

        $selectAllTheExistingMembers = DB::table('chat_group_members')
        ->select('*')
        ->where('group_chat_id', $groupChat)
        ->get();

        foreach($selectAllTheExistingMembers as $existingMembers){
            $dataArrayForExistingMembers = Arr::prepend($dataArrayForExistingMembers, [$existingMembers->uid]);
        }

        $selectUsers = DB::table('users')
        ->select('*')
        ->whereNotIn('id', $dataArrayForExistingMembers)
        ->where(DB::raw('CONCAT(first_name," ",last_name)'), 'LIKE', "%{$search}%")
        ->get();

        $dataArray = array();

        foreach($selectUsers as $users){
            $dataArray = Arr::prepend($dataArray, ["id" => $users->id, "name" => $users->first_name.' '.$users->last_name]);
        }

        return Response::json([
            'dataArray' => $dataArrayForExistingMembers,    
            'system_message' => 'success',
            'data' => $dataArray,
            'retrieving' => 'finish'            
        ], 200);

    }

    /**
     * Search users
     *
     * @param Request $request
     * @return JSON response
     */
    public function updateGroupChatMembers(Request $request){
        
        $AddedMembers = $request->input('added_members');
        $GroupChatID = $request->input('group');
        $dataArray = array();

        foreach($AddedMembers as $addMembers){
            //$dataArray = Arr::prepend($dataArray, [ "info" => $addMembers ]);
            $addNewMember = DB::table('chat_group_members')
            ->insert([
                'group_chat_id' => $GroupChatID,
                'uid' => $addMembers,
                'type' => 'Member'
            ]);
            
            // send to database
            $messageID = mt_rand(9, 999999999) + time();
                
            Chatify::newMessage([
                'id' => $messageID,
                'type' => 'group',
                'from_id' => Auth::user()->id,
                'to_id' => $GroupChatID,
                'body' => $addMembers.'-GC-'.$GroupChatID.'-added-by-'.Auth::user()->id,
                'attachment' => null,
            ]);

            // fetch message to send it with the response
            //try{
                $newID = 'group-'.$messageID;
                $messageData = Chatify::fetchMessage($newID);
            /*}catch(Exeption $e){
                return $e;
            }*/

            // send to user using pusher
            Chatify::push('private-chatify', 'messaging', [
                'type' => 'group',
                'from_id' => Auth::user()->id,
                'to_id' => $GroupChatID,
                'message' => Chatify::messageCard($messageData, 'default')
            ]);
        
        }

        return Response::json([
            "members" => $AddedMembers,
            "data" => $dataArray,
            "system_message" => 1
        ],200);
    
    }

    /**
     * Put a user in the favorites list
     *
     * @param Request $request
     * @return void
     */
    public function favorite(Request $request){
        if (Chatify::inFavorite($request['user_id'])) {
            Chatify::makeInFavorite($request['user_id'], 0);
            $status = 0;
        } else {
            Chatify::makeInFavorite($request['user_id'], 1);
            $status = 1;
        }
        return Response::json([
            'status' => @$status,
        ], 200);
    }

    /**
     * Get favorites list
     *
     * @param Request $request
     * @return void
     */
    public function getFavorites(Request $request)
    {
        $favoritesList = null;
        $favorites = Favorite::where('user_id', Auth::user()->id);
        foreach ($favorites->get() as $favorite) {
            $user = User::where('id', $favorite->favorite_id)->first();
            $favoritesList .= view('Chatify::layouts.favorite', [
                'user' => $user,
            ]);
        }
        return Response::json([
            'favorites' => $favorites->count() > 0
                ? $favoritesList
                : '<p class="message-hint"><span>Your favorite list is empty</span></p>',
        ], 200);
    }

    /**
     * Search in messenger
     *
     * @param Request $request
     * @return void
     */
    public function search(Request $request){
        $getRecords = null;
        $searchingMode = $request['searchingMode'];
        $input = trim(filter_var($request['input'], FILTER_SANITIZE_STRING));
        
        if($searchingMode == "users"){
            $records = User::where(DB::raw('CONCAT(first_name," ",last_name)'), 'LIKE', "%{$input}%");
            foreach ($records->get() as $record) {
                $getRecords .= view('Chatify::layouts.listItem', [
                    'get' => 'search_item',
                    'type' => 'user',
                    'user' => $record,
                ])->render();
            }
        }else{
            $records = DB::table('chat_groups')
            ->select('*')
            ->where('group_chat_name', 'LIKE', "%{$input}%");
            foreach ($records->get() as $record) {
                $getRecords .= view('Chatify::layouts.listItem', [
                    'get' => 'search_item',
                    'type' => 'group',
                    'group' => $record,
                ])->render();
            }
        }

        return Response::json([
            'records' => $records->count() > 0
                ? $getRecords
                : '<p class="message-hint"><span>Nothing to show.</span></p>',
            'addData' => 'html'
        ], 200);
    }

    public function removeMember(Request $request){
        $ID = $request->input('ID');
        $RemmoveMember = DB::table('chat_group_members')
        ->where('id', $ID)
        ->delete();

        if($RemmoveMember){
            return Response::json([
                "system_message" => 1,
            ]);
        }

    }
    
    public function listOfMembers(Request $request){
        $getRecords = null;
        $GC = $request->input('group_chat');
        $listGroupOfMembers = DB::table('chat_group_members')
        ->select('*')
        ->where('group_chat_id', $GC)
        ->get();

        $listGroupOfMembersCount = DB::table('chat_group_members')
        ->select('*')
        ->where('group_chat_id', $GC)
        ->count();

        foreach($listGroupOfMembers as $list){
            $contact = User::where('id', $list->uid)->first();
            $getRecords .= view('Chatify::layouts.listItem', [
                'get' => 'list_of_members',
                'contact' => $contact,
                'type' => $list->type,
                'list' => $list,
            ])->render();
        }

        return Response::json([
            'records' => $listGroupOfMembersCount > 0 ? $getRecords : '<p class="message-hint"><span>Nothing to show.</span></p>',
            'addData' => 'html'
        ], 200);

    }

    /**
     * Get shared photos
     *
     * @param Request $request
     * @return void
     */
    public function sharedPhotos(Request $request)
    {
        $shared = Chatify::getSharedPhotos($request['user_id'], $request['type']);
        $sharedPhotos = null;
        for ($i = 0; $i < count($shared); $i++) {
            $sharedPhotos .= view('Chatify::layouts.listItem', [
                'get' => 'sharedPhoto',
                'image' => $shared[$i],
            ])->render();
        }
        return Response::json([
            'shared' => count($shared) > 0 ? $sharedPhotos : '<p class="message-hint"><span>Nothing shared yet</span></p>',
        ], 200);
    }

    /**
     * Delete conversation
     *
     * @param Request $request
     * @return void
     */
    public function deleteConversation(Request $request){
        $delete = Chatify::deleteConversation($request['id'], $request['type']);
        return Response::json([
            'deleted' => $delete ? 1 : 0,
        ], 200);
    }

    public function updateSettings(Request $request)
    {
        $msg = null;
        $error = $success = 0;

        // dark mode
        if ($request['dark_mode']) {
            $request['dark_mode'] == "dark"
                ? User::where('id', Auth::user()->id)->update(['dark_mode' => 1])  // Make Dark
                : User::where('id', Auth::user()->id)->update(['dark_mode' => 0]); // Make Light
        }

        // If messenger color selected
        if ($request['messengerColor']) {

            $messenger_color = explode('-', trim(filter_var($request['messengerColor'], FILTER_SANITIZE_STRING)));
            $messenger_color = Chatify::getMessengerColors()[$messenger_color[1]];
            User::where('id', Auth::user()->id)
                ->update(['messenger_color' => $messenger_color]);
        }
        // if there is a [file]
        if ($request->hasFile('avatar')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();

            $file = $request->file('avatar');
            // if size less than 150MB
            if ($file->getSize() < 150000000) {
                if (in_array($file->getClientOriginalExtension(), $allowed_images)) {
                    // delete the older one
                    if (Auth::user()->images != config('chatify.user_avatar.default')) {
                        $path = storage_path('storage/'.Auth::user()->image);
                        if (file_exists($path)) {
                            @unlink($path);
                        }
                    }
                    // upload
                    $avatar = Str::uuid().".".$file->getClientOriginalExtension();
                    $update = User::where('id', Auth::user()->id)->update(['image' => $avatar]);
                    $file->storeAs("/storage/images/", $avatar);
                    $success = $update ? 1 : 0;
                } else {
                    $msg = "File extension not allowed!";
                    $error = 1;
                }
            } else {
                $msg = "File extension not allowed!";
                $error = 1;
            }
        }

        // send the response
        return Response::json([
            'status' => $success ? 1 : 0,
            'error' => $error ? 1 : 0,
            'message' => $error ? $msg : 0,
        ], 200);
    }

    /**
     * Set user's active status
     *
     * @param Request $request
     * @return void
     */
    public function setActiveStatus(Request $request)
    {
        $update = $request['status'] > 0
            ? User::where('id', $request['user_id'])->update(['active_status' => 1])
            : User::where('id', $request['user_id'])->update(['active_status' => 0]);
        // send the response
        return Response::json([
            'status' => $update,
        ], 200);
    }

    /**
     * Create group chat
     *
     * @param Request $request
     * @return void
     */
    public function createGroupChat(Request $request)
    {
        $GroupChatID = mt_rand(9, 999999999) + time();
        $GroupChatName = $request->input('GroupChatName');
        $GroupAvatar = $request->file('GroupChatAvatar');

        if($GroupAvatar != null){
             // allowed extensions
             $allowed_images = Chatify::getAllowedImages();
             $allowed_files  = Chatify::getAllowedFiles();
             $allowed        = array_merge($allowed_images, $allowed_files);

             // if size less than 150MB
             if ($GroupAvatar->getSize() < 150000000){
                 if (in_array($GroupAvatar->getClientOriginalExtension(), $allowed)) {
                     // upload attachment and store the new name
                     $Avatar = $GroupChatID.'.'.$GroupAvatar->getClientOriginalExtension();
                     $GroupAvatar->move(public_path('storage/users_avatar'), $Avatar);
                 } else {
                     $error_msg = "File extension not allowed!";
                 }
             } else {
                 $error_msg = "File size is too long!";
             }
        }else{
            $Avatar = 'avatar.png';
        }

        $memberArray = [];
        $favoriteByArray = [];


        $GroupMember = json_encode(["group_member" => $memberArray]);
        $FavoritedBy = json_encode(["favorited_by" => $favoriteByArray]);

        $createGroupChat = DB::table('chat_groups')
        ->insert([
            'group_chat_id' => $GroupChatID,
            'group_chat_name' => $GroupChatName,
            'avatar' => $Avatar,
            'create_by_name' => auth()->user()->first_name.' '.auth()->user()->last_name,
            'create_by' => auth()->user()->id,
            'favorited_by_id' => $FavoritedBy,
            'group_chat_members' => $GroupMember,
            'is_deleted' => 0
        ]);
        $id = DB::getPdo()->lastInsertId();
        $groupChatMembers = DB::table('chat_group_members')
        ->insert([
            'group_chat_id' => $id,
            'uid' => auth()->user()->id,
            'type' => 'Administrator'
        ]);

        
        // send to database
        $messageID = mt_rand(9, 999999999) + time();
            
        Chatify::newMessage([
            'id' => $messageID,
            'type' => 'group',
            'from_id' => Auth::user()->id,
            'to_id' => $id,
            'body' => 'GC-'.$id.'-created-by-'.Auth::user()->id,
            'attachment' => null,
        ]);
        
        //try{
            // fetch message to send it with the response
            $newID = 'group-'.$messageID;
            $messageData = Chatify::fetchMessage($newID);
        /*}catch(Exeption $e){
            return $e;
        }*/
        
        // send to user using pusher
        Chatify::push('private-chatify', 'messaging', [
            'type' => 'group',
            'from_id' => Auth::user()->id,
            'to_id' => $id,
            'message' => Chatify::messageCard($messageData, 'default')
        ]);
        
        if($createGroupChat){
            return Response::json([
                "id" => $id,
                "system_message" => 1,
                "group_chat_name" => $GroupChatName,
            ]);
        }

    }


}
