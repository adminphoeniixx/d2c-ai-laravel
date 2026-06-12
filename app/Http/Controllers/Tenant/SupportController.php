<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\SupportCategory;
use App\Models\Tenant\SupportFaq;
use App\Models\Tenant\SupportReply;
use App\Models\Tenant\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    /* ── Ticket Dashboard ─────────────────────── */

    public function index(Request $request): Response
    {
        $status = $request->input('status', 'open');
        $priority = $request->input('priority');

        SupportCategory::seedDefaults();

        $query = SupportTicket::with('category:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->latest();

        $tickets = $query->paginate(30)->withQueryString();

        $counts = [
            'open'           => SupportTicket::where('status', 'open')->count(),
            'in_progress'    => SupportTicket::where('status', 'in_progress')->count(),
            'awaiting_reply' => SupportTicket::where('status', 'awaiting_reply')->count(),
            'resolved'       => SupportTicket::where('status', 'resolved')->count(),
            'closed'         => SupportTicket::where('status', 'closed')->count(),
            'sla_breached'   => SupportTicket::where('sla_breached', true)->where('status', '!=', 'closed')->count(),
        ];

        return Inertia::render('Tenant/Support/Index', [
            'tickets'    => $tickets,
            'counts'     => $counts,
            'status'     => $status,
            'categories' => SupportCategory::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, string $tenant, SupportTicket $ticket): Response
    {
        $ticket->load(['category', 'replies' => fn ($q) => $q->orderBy('created_at')]);
        $ticket->checkSla();

        return Inertia::render('Tenant/Support/Show', [
            'ticket'     => $ticket,
            'categories' => SupportCategory::where('is_active', true)->get(['id', 'name']),
            'agents'     => \App\Models\User::where('company_id', app('current_company')->id)->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject'        => ['required', 'string', 'max:200'],
            'description'    => ['required', 'string'],
            'priority'       => ['required', 'in:low,medium,high,urgent'],
            'category_id'    => ['nullable', 'exists:support_categories,id'],
            'customer_name'  => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'order_number'   => ['nullable', 'string', 'max:60'],
            'source'         => ['nullable', 'in:portal,email,phone,whatsapp'],
        ]);

        $category = $validated['category_id'] ? SupportCategory::find($validated['category_id']) : null;

        $ticket = SupportTicket::create(array_merge($validated, [
            'ticket_number' => SupportTicket::generateNumber(),
            'sla_hours'     => $category->sla_hours ?? 24,
            'source'        => $validated['source'] ?? 'portal',
        ]));

        // Bot auto-reply if category has one
        if ($category && $category->auto_reply) {
            SupportReply::create([
                'ticket_id'   => $ticket->id,
                'body'        => $category->auto_reply,
                'sender_type' => 'bot',
                'sender_name' => 'Support Bot',
            ]);
        }

        return redirect()->route('tenant.support.show', ['tenant' => $request->route('tenant'), 'ticket' => $ticket->id])
            ->with('success', "Ticket {$ticket->ticket_number} created.");
    }

    /* ── Replies ──────────────────────────────── */

    public function reply(Request $request, string $tenant, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'body'             => ['required', 'string'],
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        $user = auth()->user();

        SupportReply::create([
            'ticket_id'        => $ticket->id,
            'body'             => $validated['body'],
            'sender_type'      => 'agent',
            'sender_name'      => $user->name,
            'sender_email'     => $user->email,
            'user_id'          => $user->id,
            'is_internal_note' => $validated['is_internal_note'] ?? false,
        ]);

        // Update ticket status
        $updates = [];
        if (!$ticket->first_responded_at) {
            $updates['first_responded_at'] = now();
        }
        if ($ticket->status === 'open' || $ticket->status === 'awaiting_reply') {
            $updates['status'] = 'in_progress';
        }
        if (!$ticket->assigned_to) {
            $updates['assigned_to'] = $user->id;
            $updates['assigned_at'] = now();
        }
        if ($updates) $ticket->update($updates);

        return back()->with('success', 'Reply sent.');
    }

    /* ── Status & Assignment ──────────────────── */

    public function updateStatus(Request $request, string $tenant, SupportTicket $ticket): RedirectResponse
    {
        $validated = $request->validate([
            'status'      => ['required', 'in:open,in_progress,awaiting_reply,resolved,closed'],
            'assigned_to' => ['nullable', 'integer'],
        ]);

        $updates = ['status' => $validated['status']];

        if (isset($validated['assigned_to'])) {
            $updates['assigned_to'] = $validated['assigned_to'];
            if (!$ticket->assigned_at) $updates['assigned_at'] = now();
        }

        if ($validated['status'] === 'resolved') $updates['resolved_at'] = now();
        if ($validated['status'] === 'closed') $updates['closed_at'] = now();

        $ticket->update($updates);

        return back()->with('success', 'Ticket updated.');
    }

    /* ── FAQ Management ───────────────────────── */

    public function faqs(): Response
    {
        return Inertia::render('Tenant/Support/Faqs', [
            'faqs'       => SupportFaq::with('category:id,name')->orderBy('sort_order')->get(),
            'categories' => SupportCategory::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:support_categories,id'],
            'question'    => ['required', 'string', 'max:300'],
            'answer'      => ['required', 'string'],
        ]);
        SupportFaq::create($validated);
        return back()->with('success', 'FAQ added.');
    }

    public function updateFaq(Request $request, string $tenant, SupportFaq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'question'    => ['required', 'string', 'max:300'],
            'answer'      => ['required', 'string'],
            'is_active'   => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'exists:support_categories,id'],
        ]);
        $faq->update($validated);
        return back()->with('success', 'FAQ updated.');
    }

    public function destroyFaq(Request $request, string $tenant, SupportFaq $faq): RedirectResponse
    {
        $faq->delete();
        return back()->with('success', 'FAQ deleted.');
    }

    /* ── Categories ───────────────────────────── */

    public function categories(): Response
    {
        SupportCategory::seedDefaults();
        return Inertia::render('Tenant/Support/Categories', [
            'categories' => SupportCategory::withCount('tickets')->orderBy('sort_order')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'auto_reply' => ['nullable', 'string'],
            'sla_hours'  => ['required', 'integer', 'min:1'],
        ]);
        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        SupportCategory::create($validated);
        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, string $tenant, SupportCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:80'],
            'auto_reply' => ['nullable', 'string'],
            'sla_hours'  => ['required', 'integer', 'min:1'],
            'is_active'  => ['nullable', 'boolean'],
        ]);
        $category->update($validated);
        return back()->with('success', 'Category updated.');
    }
}
