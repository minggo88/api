<?php

//namespace io\codef\api;

class EasyCodefConstant {
    protected const OAUTH_DOMAIN = "https://oauth.codef.io";
    protected const GET_TOKEN = "/oauth/token";
    
    protected const SANDBOX_DOMAIN = "https://sandbox.codef.io";
    protected const SANDBOX_CLIENT_ID = "ef27cfaa-10c1-4470-adac-60ba476273f9";
    protected const SANDBOX_CLIENT_SECRET = "83160c33-9045-4915-86d8-809473cdf5c3";
    
    protected const DEMO_DOMAIN = "https://development.codef.io";
    protected const API_DOMAIN = "https://api.codef.io";
    
    protected const RESULT = "result";
    protected const CODE = "code";
    protected const MESSAGE = "message";
    protected const EXTRA_MESSAGE = "extraMessage";
    protected const DATA = "data";
    protected const ACCOUNT_LIST = "accountList";
    protected const CONNECTED_ID = "connectedId";
    
    protected static $INVALID_TOKEN = "invalid_token";
    protected static $ACCESS_DENIED = "access_denied";
    
    protected const CREATE_ACCOUNT = "/v1/account/create";
    protected const ADD_ACCOUNT = "/v1/account/add";
    protected const UPDATE_ACCOUNT = "/v1/account/update";
    protected const DELETE_ACCOUNT = "/v1/account/delete";
    protected const GET_ACCOUNT_LIST = "/v1/account/list";
    protected const GET_CID_LIST = "/v1/account/connectedId-list";
}