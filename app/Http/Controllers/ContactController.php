<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\User;
use App\Notifications\NewContactMessageNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private PushNotificationService $push) {}

    // POST /api/contact — public, protégé par rate limiting (voir routes/api.php)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'nullable|string|max:100',
            'message' => 'required|string|max:5000',
        ]);

        $contact = Contact::create($validated);

        $admins = User::where('role', 'Admin')->get();

        foreach ($admins as $admin) {
            $admin->notify(new NewContactMessageNotification($contact));
            $this->push->sendToUser(
                $admin,
                'Nouveau message de contact',
                "{$contact->name} : {$contact->subject}",
                ['type' => 'new_contact', 'contact_id' => $contact->id]
            );
        }

        return response()->json(['message' => 'Message envoyé, notre équipe vous répondra rapidement.'], 201);
    }

    // GET /api/admin/contacts — réservé staff (middleware 'admin')
    public function index(Request $request): JsonResponse
    {
        $query = Contact::query()->latest();

        if ($request->boolean('unread_only')) {
            $query->where('is_read', false);
        }

        return response()->json($query->paginate(20));
    }

    // POST /api/admin/contacts/{contact}/read
    public function markAsRead(Contact $contact): JsonResponse
    {
        $contact->update(['is_read' => true]);

        return response()->json(['message' => 'Marqué comme lu']);
    }
}
