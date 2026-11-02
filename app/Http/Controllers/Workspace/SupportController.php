<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\WorkspaceAccess;
use App\Services\WorkspaceSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Support tickets for the web workspaces (session context) and the mobile API (own tickets only). */
class SupportController extends Controller
{
    public function __construct(private WorkspaceAccess $access, private WorkspaceSupport $support) {}

    private function context(Request $request): array
    {
        return $this->access->resolve($request->user(), $request->input('context', $request->session()->get('workspace_context')));
    }

    private function rules(array $schoolIds, bool $schoolRequired): array
    {
        return ['subject' => 'required|string|max:160', 'body' => 'required|string|max:5000',
            'category' => ['required', Rule::in(array_keys(WorkspaceSupport::CATEGORIES))], 'priority' => ['nullable', Rule::in(array_keys(WorkspaceSupport::PRIORITIES))],
            'school_id' => [$schoolRequired ? 'required' : 'nullable', 'integer', Rule::in($schoolIds)]];
    }

    private function view(Request $request, array $context, string $page, array $data)
    {
        return view('workspace.'.$page, $data + ['context' => $context, 'contexts' => $this->access->webContexts($request->user()),
            'access' => $this->access, 'page' => 'support', 'handler' => $this->support->handles($context)]);
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $request->validate(['status' => ['nullable', Rule::in(array_keys(WorkspaceSupport::STATUSES))]]);
        $tickets = $this->support->visible($request->user(), $context)->when($request->status, fn ($q) => $q->where('t.status', $request->status))
            ->orderByRaw("FIELD(t.status, 'open', 'in_progress', 'resolved', 'closed')")->orderByDesc('t.updated_at')->paginate(20)->withQueryString();
        $counts = $this->support->visible($request->user(), $context)->reorder()->selectRaw('t.status, COUNT(*) as total')->groupBy('t.status')->pluck('total', 'status');
        return $this->view($request, $context, 'support', compact('tickets', 'counts'));
    }

    public function store(Request $request)
    {
        $context = $this->context($request);
        $data = $request->validate($this->rules(array_keys($context['schools']), $context['role'] !== 'super_admin' && count($context['schools']) > 1));
        $school = $data['school_id'] ?? (count($context['schools']) === 1 ? array_key_first($context['schools']) : null);
        $id = $this->support->create($request->user(), $data, $school);
        return redirect()->route('workspace.support-ticket', ['id' => $id, 'context' => $context['id']])->with('workspace_success', 'Ticket #'.$id.' created. Our support team will reply here.');
    }

    private function ticket(Request $request, ?array $context, int $id): object
    {
        $ticket = $this->support->visible($request->user(), $context)->where('t.id', $id)->first();
        abort_unless($ticket, 404);
        return $ticket;
    }

    public function show(Request $request, int $id)
    {
        $context = $this->context($request);
        $ticket = $this->ticket($request, $context, $id);
        $handler = $this->support->handles($context);
        return $this->view($request, $context, 'support-ticket', ['ticket' => $ticket, 'replies' => $this->support->replies($ticket, $handler),
            'assignee' => $ticket->assigned_to ? DB::table('users')->where('id', $ticket->assigned_to)->value('first_name') : null]);
    }

    public function reply(Request $request, int $id)
    {
        $context = $this->context($request);
        $ticket = $this->ticket($request, $context, $id);
        $data = $request->validate(['body' => 'required|string|max:5000', 'internal' => 'nullable|boolean']);
        $this->support->reply($request->user(), $ticket, $data['body'], (bool) ($data['internal'] ?? false), $this->support->handles($context));
        return back()->with('workspace_success', ($data['internal'] ?? false) ? 'Internal note added.' : 'Reply sent.');
    }

    public function status(Request $request, int $id)
    {
        $context = $this->context($request);
        $ticket = $this->ticket($request, $context, $id);
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(WorkspaceSupport::STATUSES))], 'assign' => 'nullable|in:me']);
        $handler = $this->support->handles($context);
        // Requesters may only close their own ticket; every other change belongs to support staff.
        abort_unless($handler || ((int) $ticket->requester_id === (int) $request->user()->id && ($data['status'] ?? null) === 'closed' && empty($data['assign'])), 403);
        $values = ['updated_at' => now()];
        if (!empty($data['status'])) { $values['status'] = $data['status']; $values['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? now() : null; }
        if (!empty($data['assign'])) { $values['assigned_to'] = $request->user()->id; }
        DB::table('workspace_support_tickets')->where('id', $ticket->id)->update($values);
        $this->access->audit($request->user(), $ticket->school_id, 'support.ticket.'.($data['status'] ?? 'assigned'), $ticket->id);
        return back()->with('workspace_success', 'Ticket updated.');
    }

    // Mobile API: authenticated users raise and follow their own tickets.

    public function apiIndex(Request $request)
    {
        return response()->json(['tickets' => $this->support->visible($request->user(), null)->orderByDesc('t.updated_at')->limit(50)
            ->get(['t.id', 't.subject', 't.category', 't.priority', 't.status', 't.school_id', 's.school_name', 't.created_at', 't.updated_at'])]);
    }

    public function apiStore(Request $request)
    {
        abort_unless((int) $request->user()->status, 403);
        abort_if(DB::table('workspace_support_tickets')->where('requester_id', $request->user()->id)->where('created_at', '>', now()->subDay())->count() >= 10, 429, 'Too many tickets today.');
        $data = $request->validate($this->rules($this->support->memberSchools($request->user()), false));
        $id = $this->support->create($request->user(), $data, $data['school_id'] ?? null);
        return response()->json(['message' => 'Ticket created.', 'id' => $id], 201);
    }

    public function apiShow(Request $request, int $id)
    {
        $ticket = $this->ticket($request, null, $id);
        return response()->json(['ticket' => collect($ticket)->except(['email', 'assigned_to']), 'replies' => $this->support->replies($ticket, false)->map(fn ($r) => [
            'id' => $r->id, 'body' => $r->body, 'created_at' => $r->created_at, 'from_support' => (int) $r->author_id !== (int) $ticket->requester_id,
            'author' => (int) $r->author_id === (int) $ticket->requester_id ? $r->first_name : 'PTM Support'])]);
    }

    public function apiReply(Request $request, int $id)
    {
        $ticket = $this->ticket($request, null, $id);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $this->support->reply($request->user(), $ticket, $data['body'], false, false);
        return response()->json(['message' => 'Reply sent.'], 201);
    }
}
