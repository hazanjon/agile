<?php
class ServerController extends BaseController {
    
    /**
     * create a random event assigned to a user
     */
    public function createEvent() {
        // get random user
        // create event
        // add to queue
        
        // return something for now
    }
    
    /**
     * function to call when an event is completed
     */
    public function completeEvent() {
        // mark an event as completed
    }
    
    /**
     * call this when an event isn't completed
     */
    public function failEvent() {
        // maybe use this when they enter the wrong value?
    }
    
    /**
     * handle user login
     * not sure with this one as might be using pusher
     */
    public function login() {
        
    }
    
    /**
     * run initial game setup
     */
    public function createGame() {
        
    }
    
    protected $pusher;
    
    /**
     * create the pusher connection
     */
    protected function setupPusher() {
        //$this->pusher = new Pusher(Config::get('pusher_app_key'), Config::get('pusher_app_secret'), Config::get('pusher_app_id')');
        
    }
    
}