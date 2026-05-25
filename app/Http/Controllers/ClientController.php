<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    // /api/client/:unique_id
    public function getClient(Request $request, $unique_id)
    {
        // Validate the unique_id parameter
        if (empty($unique_id) || strlen($unique_id) > 255) {
            return response()->json([
                'message' => 'Invalid unique_id parameter'
            ], 400);
        }

        // Fetch the client by unique_id
        $client = Client::where('unique_id', $unique_id)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $client
        ]);
    }

    // insert or update client
    public function storeOrUpdateClient(Request $request)
    {
        $validatedData = $request->validate([
            'unique_id' => 'required|string|max:255',
            'ip' => 'required|ip',  
            'last_page' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:10',
            'action' => 'nullable|string|max:255',
            'ban' => 'nullable|boolean',
        ]);

        try {
            // Check if client with this unique_id already exists
            $client = Client::updateOrCreate(
                ['unique_id' => $validatedData['unique_id']],
                $validatedData
            );

            return response()->json([
                'message' => 'Client has been updated successfully.',
                'data' => $client
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update client. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }

    }

    // Update Client Action
    public function updateClientAction(Request $request, $unique_id)
    {

        $client = Client::where('unique_id', $unique_id)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        try {
            $client->action = '';
            $client->save();

            return response()->json([
                'message' => 'Client action updated successfully.',
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update client action. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
        
    }

    //Unban or Ban Client
    public function banOrUnbanClient(Request $request, $unique_id)
    {
        $client = Client::where('unique_id', $unique_id)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        try {
            $client->ban = !$client->ban; // Toggle ban status
            $client->save();

            return response()->json([
                'message' => $client->ban ? 'Client has been banned successfully.' : 'Client has been unbanned successfully.',
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update ban status. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function banClient(Request $request, $unique_id)
    {
        $client = Client::where('unique_id', $unique_id)->first();

        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }

        if ($client->ban) {
            return response()->json([
                'message' => 'Client is already banned.'
            ]);
        }

        try {
            $client->ban = true; // Set ban status
            $client->save();

            return response()->json([
                'message' => 'Client has been banned successfully.',
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to ban client. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // List all clients (authenticated)
    public function index(Request $request)
    {
        try {
            $clients = Client::orderBy('created_at', 'desc')->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $clients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load clients.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Show specific client (authenticated)
    public function show(Request $request, $id)
    {
        try {
            $client = Client::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Client not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Show specific client by unique_id (authenticated)
    public function showByUniqueId(Request $request, $unique_id)
    {
        try {
            $client = Client::where('unique_id', $unique_id)->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Client not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    // Create action for client
    public function createAction(Request $request, $id)
    {
        $validatedData = $request->validate([
            'action_type' => 'required|string|max:255',
        ]);

        try {
            $client = Client::findOrFail($id);
            
            // Update client action
            $client->action = $validatedData['action_type'];
            $client->save();

            return response()->json([
                'message' => 'Action created successfully.',
                'data' => [
                    'id' => time(), // Mock action ID
                    'action_type' => $validatedData['action_type'],
                    'status' => 'completed',
                    'created_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create action for client by unique_id
    public function createActionByUniqueId(Request $request, $unique_id)
    {
        $validatedData = $request->validate([
            'action_type' => 'required|string|max:255',
        ]);

        try {
            $client = Client::where('unique_id', $unique_id)->firstOrFail();
            
            // Update client action
            $client->action = $validatedData['action_type'];
            $client->save();

            return response()->json([
                'message' => 'Action created successfully.',
                'data' => [
                    'id' => time(), // Mock action ID
                    'action_type' => $validatedData['action_type'],
                    'status' => 'completed',
                    'created_at' => now()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get actions for client
    public function getActions(Request $request, $id)
    {
        try {
            $client = Client::findOrFail($id);
            
            // Mock actions data - in a real app, this would come from an actions table
            $actions = [];
            if ($client->action) {
                $actions[] = [
                    'id' => 1,
                    'action_type' => $client->action,
                    'status' => 'completed',
                    'created_at' => $client->updated_at
                ];
            }

            return response()->json($actions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load actions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get actions for client by unique_id
    public function getActionsByUniqueId(Request $request, $unique_id)
    {
        try {
            $client = Client::where('unique_id', $unique_id)->firstOrFail();
            
            // Mock actions data - in a real app, this would come from an actions table
            $actions = [];
            if ($client->action) {
                $actions[] = [
                    'id' => 1,
                    'action_type' => $client->action,
                    'status' => 'completed',
                    'created_at' => $client->updated_at
                ];
            }

            return response()->json($actions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to load actions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete action
    public function deleteAction(Request $request, $id, $actionId)
    {
        try {
            $client = Client::findOrFail($id);
            
            // In a real app, you would delete from actions table
            // For now, just clear the action field
            $client->action = null;
            $client->save();

            return response()->json([
                'message' => 'Action deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create anti-duplicate
    public function createAntiDuplicate(Request $request, $id)
    {
        $validatedData = $request->validate([
            'cardnumber' => 'required|string|max:255',
            'unique_id' => 'required|string|max:255',
        ]);

        try {
            $client = Client::findOrFail($id);
            
            // In a real app, you would store this in an anti_duplicates table
            // For now, just update the client action
            $client->action = 'anti_duplicate_' . $validatedData['cardnumber'];
            $client->save();

            return response()->json([
                'message' => 'Anti-duplicate action created successfully.',
                'data' => [
                    'cardnumber' => $validatedData['cardnumber'],
                    'client_id' => $id
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create anti-duplicate action.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Send custom message
    public function sendCustomMessage(Request $request, $id)
    {
        $validatedData = $request->validate([
            'heading' => 'required|string|max:255',
            'message_text' => 'required|string',
            'option_type' => 'required|string|in:label,link',
            'label' => 'nullable|string|max:255',
            'link_attachment' => 'nullable|url',
        ]);

        try {
            $client = Client::findOrFail($id);
            
            // In a real app, you would store this in a messages table
            // For now, just update the client action
            $client->action = 'custom_message_' . $validatedData['heading'];
            $client->save();

            return response()->json([
                'message' => 'Custom message sent successfully.',
                'data' => $validatedData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send custom message.',
                'error' => $e->getMessage()
            ], 500);
        }
    }    
}
