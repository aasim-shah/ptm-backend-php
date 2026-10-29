<?php
namespace App\Services;
use App\Models\User;
class WorkspaceFiles
{
    public function __construct(private WorkspaceDashboard $dashboard,private WorkspaceAccess $access) {}
    public function resource(User $user,array $context,string $kind,int $id)
    {
        return match ($kind) {
            'message'=>$this->dashboard->messages($user,$context)->where('m.id',$id)->first(),
            'update'=>$this->dashboard->updates($user,$context)->where('u.id',$id)->first(),
            'notice','event'=>$this->dashboard->posts($context,$kind)->where('id',$id)->first(),
            default=>null,
        };
    }
    public function mayUpload(User $user,array $context,string $kind,$resource): bool
    {
        if (!$resource) { return false; }
        return match ($kind) {
            'message'=>(int)$resource->sender_id===(int)$user->id,
            'update'=>(int)$resource->author_id===(int)$user->id && $this->access->can($context,'updates.write'),
            'notice'=>(int)$resource->author_id===(int)$user->id && $this->access->can($context,'notices.publish'),
            'event'=>(int)$resource->author_id===(int)$user->id && $this->access->can($context,'events.manage'),
            default=>false,
        };
    }
}
