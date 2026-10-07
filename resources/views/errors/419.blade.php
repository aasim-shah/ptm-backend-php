@extends('errors::minimal')

@section('title', __('Page Expired'))
<style>
    .main-error{
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
    }
    .relative{
        display: none !important;
    }
    button{
        background-color: #19b3f3;
        border: none;
        color: white;
        padding: 15px 32px;
        text-align: center;
        text-decoration: none;
        display: inline-block;
        font-size: 16px;
        margin: 4px 2px;
        cursor: pointer;
        border-radius: 8px;
    }

</style>
<div class="row main-error">
    <div class="col-md-4"></div>
    <div class="col-md-4 text-center"><p>Session is expired</p> <br>
        <a href="/login"> <button type="button" class="btn btn-secondary">{{ __('Login') }}</button></a></div>
    <div class="col-md-4"></div>

</div>
