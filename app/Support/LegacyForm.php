<?php
namespace App\Support;

/** Small escaped adapter for the form helpers used by retained Blade screens. */
class LegacyForm
{
    private $model = null;
    private function attrs(array $attributes): string
    {
        $html='';
        foreach ($attributes as $key=>$value) {
            if (is_int($key)) { $key=(string)$value; $value=true; }
            if ($value===false || $value===null) { continue; }
            if ($value===true) { $html.=' '.e($key); continue; }
            $html.=' '.e($key).'="'.e(is_array($value)?implode(' ',$value):(string)$value).'"';
        }
        return $html;
    }
    private function value(string $name,$default)
    {
        $key=str_replace(['[',']'],['.',''],$name); $key=rtrim($key,'.');
        return old($key,$default ?? data_get($this->model,$key));
    }
    public function open(array $options=[]): string
    {
        $method=strtoupper($options['method']??'POST'); $action=$options['url']??null;
        if (isset($options['route'])) { $args=(array)$options['route']; $action=route(array_shift($args),$args); }
        unset($options['route'],$options['url'],$options['method']);
        if (isset($options['files'])) { if ($options['files']) { $options['enctype']='multipart/form-data'; } unset($options['files']); }
        $html='<form'.$this->attrs(['method'=>$method==='GET'?'GET':'POST','action'=>$action??url()->current()]+$options).'>';
        if ($method!=='GET') { $html.=csrf_field(); }
        if (!in_array($method,['GET','POST'],true)) { $html.=method_field($method); }
        return $html;
    }
    public function model($model,array $options=[]): string { $this->model=$model; return $this->open($options); }
    public function close(): string { $this->model=null; return '</form>'; }
    private function input(string $type,string $name,$value=null,array $attributes=[]): string
    {
        if ($type!=='password') { $value=$this->value($name,$value); }
        return '<input'.$this->attrs(['type'=>$type,'name'=>$name,'value'=>$type==='password'?null:$value]+$attributes).'>';
    }
    public function text(string $n,$v=null,array $a=[]): string { return $this->input('text',$n,$v,$a); }
    public function tel(string $n,$v=null,array $a=[]): string { return $this->input('tel',$n,$v,$a); }
    public function number(string $n,$v=null,array $a=[]): string { return $this->input('number',$n,$v,$a); }
    public function date(string $n,$v=null,array $a=[]): string { return $this->input('date',$n,$v,$a); }
    public function time(string $n,$v=null,array $a=[]): string { return $this->input('time',$n,$v,$a); }
    public function hidden(string $n,$v=null,array $a=[]): string { return $this->input('hidden',$n,$v,$a); }
    public function password(string $n,array $a=[]): string { return $this->input('password',$n,null,$a); }
    public function submit(string $value,array $a=[]): string { return '<input'.$this->attrs(['type'=>'submit','value'=>$value]+$a).'>'; }
    public function textarea(string $name,$value=null,array $a=[]): string { return '<textarea'.$this->attrs(['name'=>$name]+$a).'>'.e($this->value($name,$value)).'</textarea>'; }
    public function radio(string $name,$value=null,$checked=null,array $a=[]): string
    {
        $selected=$this->value($name,null); if ($selected!==null) { $checked=(string)$selected===(string)$value; }
        return '<input'.$this->attrs(['type'=>'radio','name'=>$name,'value'=>$value,'checked'=>(bool)$checked]+$a).'>';
    }
    public function checkbox(string $name,$value=1,$checked=null,array $a=[]): string
    {
        $selected=$this->value($name,null); if ($selected!==null) { $checked=in_array((string)$value,array_map('strval',(array)$selected),true); }
        return '<input'.$this->attrs(['type'=>'checkbox','name'=>$name,'value'=>$value,'checked'=>(bool)$checked]+$a).'>';
    }
    public function select(string $name,$options=[],$selected=null,array $a=[]): string
    {
        $selected=array_map('strval',(array)$this->value($name,$selected)); $html='<select'.$this->attrs(['name'=>$name]+$a).'>';
        foreach ($options as $value=>$label) { $html.='<option'.$this->attrs(['value'=>$value,'selected'=>in_array((string)$value,$selected,true)]).'>'.e($label).'</option>'; }
        return $html.'</select>';
    }
}
