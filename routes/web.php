<?php
/**
 * -----------------------------------------------------------------
 * NOTE : There is two routes has a name (user & group),
 * any change in these two route's name may cause an issue
 * if not modified in all places that used in (e.g Chatify class,
 * Controllers, chatify javascript file...).
 * -----------------------------------------------------------------
 */


/*
* This is the main app route [Chatify Messenger]
*/
Route::get('/', 'MessagesController@index')->name(config('chatify.path'));

/**
 *  Fetch info for specific id [user/group]
 */
Route::post('/idInfo', 'MessagesController@idFetchData');

/**
 * Send message route
 */
Route::post('/sendMessage', 'MessagesController@send')->name('send.message');

/**
 * Fetch messages 
 */
Route::post('/fetchMessages', 'MessagesController@fetch')->name('fetch.messages');

/**
 * Download attachments route to create a downloadable links
 */
Route::get('/download/{fileName}', 'MessagesController@download')->name(config('chatify.attachments.route'));

/**
 * Authintication for pusher private channels
 */
Route::post('/chat/auth', 'MessagesController@pusherAuth')->name('pusher.auth');

/**
 * Make messages as seen
 */
Route::post('/makeSeen', 'MessagesController@seen')->name('messages.seen');

/**
 * Get contacts
 */
Route::post('/getContacts', 'MessagesController@getContacts')->name('contacts.get');

/**
 * Update contact item data
 */
Route::post('/updateContacts', 'MessagesController@updateContactItem')->name('contacts.update');


/**
 * Star in favorite list
 */
Route::post('/star', 'MessagesController@favorite')->name('star');

/**
 * get favorites list
 */
Route::post('/favorites', 'MessagesController@getFavorites')->name('favorites');

/**
 * Search in messenger
 */
Route::post('/search', 'MessagesController@search')->name('search');

/**
 * Get shared photos
 */
Route::post('/shared', 'MessagesController@sharedPhotos')->name('shared');

/**
 * Delete Conversation
 */
Route::post('/deleteConversation', 'MessagesController@deleteConversation')->name('conversation.delete');

/**
 * Delete Conversation
 */
Route::post('/updateSettings', 'MessagesController@updateSettings')->name('avatar.update');

/**
 * Set active status
 */
Route::post('/setActiveStatus', 'MessagesController@setActiveStatus')->name('activeStatus.set');


/*
* [Group] view by id
*/
Route::get('/group/{id}', 'MessagesController@index')->name('group');

Route::get('/{id}', 'MessagesController@index')->name('user');

Route::post('/createGroupChat', 'MessagesController@createGroupChat')->name('create.group.chat');

Route::post('/selectUsers', 'MessagesController@selectUsers')->name('select.users');

Route::post('/updateGroupChatMembers', 'MessagesController@updateGroupChatMembers')->name('update.group.chat');

Route::post('/listOfMembers', 'MessagesController@listOfMembers')->name('list.members');

Route::post('/removeMember', 'MessagesController@removeMember')->name('remove.member');

Route::post('/getTheGroupInfo', 'MessagesController@getTheGroupInfo')->name('get.group.info');

Route::post('/updateGroupChat', 'MessagesController@updateGroupChat')->name('update.group.chat');

Route::post('/leaveGroup', 'MessagesController@leaveGroup')->name('leave.group');
