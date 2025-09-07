<?php
include 'partials/dbconnect.php';
session_start();

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? null;

if (!$user_id || !$user_type) {
    header('Location: ../index.php');
    exit;
}

// Get user details based on type
if ($user_type === 'parent') {
    $stmt = $conn->prepare("SELECT id, full_name FROM parents WHERE user_id = ?");
} else {
    $stmt = $conn->prepare("SELECT id, full_name FROM teachers WHERE user_id = ?");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result()->fetch_assoc();

if (!$user_result) {
    echo "No user found!";
    exit;
}

$current_user_id = $user_result['id'];
$current_user_name = $user_result['full_name'];

// Get conversations for the current user
if ($user_type === 'parent') {
    // For parents: get all teachers they've messaged or received messages from
    $conversations_query = "
        SELECT DISTINCT t.id, t.full_name, t.email, 
               (SELECT message FROM messages 
                WHERE (sender_id = ? AND sender_type = 'parent' AND receiver_id = t.id) 
                   OR (receiver_id = ? AND sender_type = 'teacher' AND sender_id = t.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages 
                WHERE (sender_id = ? AND sender_type = 'parent' AND receiver_id = t.id) 
                   OR (receiver_id = ? AND sender_type = 'teacher' AND sender_id = t.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM teachers t
        JOIN messages m ON (m.sender_id = t.id AND m.sender_type = 'teacher' AND m.receiver_id = ?) 
                       OR (m.receiver_id = t.id AND m.sender_id = ? AND m.sender_type = 'parent')
        ORDER BY last_message_time DESC
    ";
    $stmt = $conn->prepare($conversations_query);
    $stmt->bind_param("iiiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id);
} else {
    // For teachers: get all parents they've messaged or received messages from
    $conversations_query = "
        SELECT DISTINCT p.id, p.full_name, p.email,
               (SELECT message FROM messages 
                WHERE (sender_id = ? AND sender_type = 'teacher' AND receiver_id = p.id) 
                   OR (receiver_id = ? AND sender_type = 'parent' AND sender_id = p.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages 
                WHERE (sender_id = ? AND sender_type = 'teacher' AND receiver_id = p.id) 
                   OR (receiver_id = ? AND sender_type = 'parent' AND sender_id = p.id) 
                ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM parents p
        JOIN messages m ON (m.sender_id = p.id AND m.sender_type = 'parent' AND m.receiver_id = ?) 
                       OR (m.receiver_id = p.id AND m.sender_id = ? AND m.sender_type = 'teacher')
        ORDER BY last_message_time DESC
    ";
    $stmt = $conn->prepare($conversations_query);
    $stmt->bind_param("iiiiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id);
}

$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get messages for a specific conversation if selected
$selected_contact_id = $_GET['contact_id'] ?? null;
$messages = [];
$selected_contact = null;

if ($selected_contact_id) {
    // Get contact details
    if ($user_type === 'parent') {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM teachers WHERE id = ?");
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email FROM parents WHERE id = ?");
    }
    $stmt->bind_param("i", $selected_contact_id);
    $stmt->execute();
    $selected_contact = $stmt->get_result()->fetch_assoc();
    
    // Get messages between current user and selected contact
    if ($user_type === 'parent') {
        $messages_query = "
            SELECT m.*, 'teacher' as contact_type, t.full_name as contact_name
            FROM messages m 
            JOIN teachers t ON m.sender_id = t.id
            WHERE m.sender_type = 'teacher' AND m.sender_id = ? AND m.receiver_id = ?
            UNION
            SELECT m.*, 'parent' as contact_type, p.full_name as contact_name
            FROM messages m 
            JOIN parents p ON m.sender_id = p.id
            WHERE m.sender_type = 'parent' AND m.sender_id = ? AND m.receiver_id = ?
            ORDER BY created_at ASC
        ";
        $stmt = $conn->prepare($messages_query);
        $stmt->bind_param("iiii", $selected_contact_id, $current_user_id, $current_user_id, $selected_contact_id);
    } else {
        $messages_query = "
            SELECT m.*, 'parent' as contact_type, p.full_name as contact_name
            FROM messages m 
            JOIN parents p ON m.sender_id = p.id
            WHERE m.sender_type = 'parent' AND m.sender_id = ? AND m.receiver_id = ?
            UNION
            SELECT m.*, 'teacher' as contact_type, t.full_name as contact_name
            FROM messages m 
            JOIN teachers t ON m.sender_id = t.id
            WHERE m.sender_type = 'teacher' AND m.sender_id = ? AND m.receiver_id = ?
            ORDER BY created_at ASC
        ";
        $stmt = $conn->prepare($messages_query);
        $stmt->bind_param("iiii", $selected_contact_id, $current_user_id, $current_user_id, $selected_contact_id);
    }
    
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Mark messages as read
    $update_query = "UPDATE messages SET is_read = TRUE WHERE receiver_id = ? AND sender_id = ? AND sender_type = ?";
    $sender_type = $user_type === 'parent' ? 'teacher' : 'parent';
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iis", $current_user_id, $selected_contact_id, $sender_type);
    $stmt->execute();
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $selected_contact_id) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $insert_query = "INSERT INTO messages (sender_id, receiver_id, sender_type, message) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iiss", $current_user_id, $selected_contact_id, $user_type, $message);
        
        if ($stmt->execute()) {
            // Refresh the page to show the new message
            header("Location: messages.php?contact_id=" . $selected_contact_id);
            exit;
        } else {
            $error = "Failed to send message. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Communication System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .message-incoming { background-color: #e0f7fa; border-left: 4px solid #0097a7; }
        .message-outgoing { background-color: #f3e5f5; border-left: 4px solid #7b1fa2; }
        .chat-container { height: calc(100vh - 200px); }
        .conversations-list { height: calc(100vh - 200px); overflow-y: auto; }
        .messages-area { height: calc(100vh - 200px); overflow-y: auto; }
        .active-conversation { background-color: #e3f2fd; }
        @media (max-width: 768px) {
            .chat-container { height: auto; }
            .conversations-list, .messages-area { height: 400px; }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <header class="bg-blue-600 text-white shadow-lg">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <div class="flex items-center">
                <i class="fas fa-comments text-2xl mr-3"></i>
                <h1 class="text-2xl font-bold">School Communication Portal</h1>
            </div>
            <div class="flex items-center">
                <span class="mr-4">Welcome, <?= htmlspecialchars($current_user_name) ?> (<?= ucfirst($user_type) ?>)</span>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-6 chat-container">
                <!-- Conversations List -->
                <div class="w-full md:w-1/3 bg-gray-50 rounded-lg shadow-inner p-4 conversations-list">
                    <h2 class="text-xl font-semibold mb-4 text-gray-700 border-b pb-2">Conversations</h2>
                    
                    <!-- Search Box -->
                    <div class="relative mb-4">
                        <input type="text" placeholder="Search conversations..." class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                    </div>
                    
                    <!-- Conversation List -->
                    <div class="space-y-3">
                        <?php if (empty($conversations)): ?>
                            <p class="text-gray-500 text-center py-4">No conversations yet.</p>
                        <?php else: ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <a href="messages.php?contact_id=<?= $conversation['id'] ?>" class="block">
                                    <div class="p-3 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition cursor-pointer <?= ($selected_contact_id == $conversation['id']) ? 'active-conversation' : 'bg-white' ?>">
                                        <div class="flex items-center">
                                            <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                                <?= substr(htmlspecialchars($conversation['full_name']), 0, 1) ?>
                                            </div>
                                            <div class="flex-1">
                                                <h3 class="font-semibold"><?= htmlspecialchars($conversation['full_name']) ?></h3>
                                                <p class="text-sm text-gray-600 truncate"><?= !empty($conversation['last_message']) ? htmlspecialchars($conversation['last_message']) : 'No messages yet' ?></p>
                                            </div>
                                            <div class="text-right">
                                                <?php if (!empty($conversation['last_message_time'])): ?>
                                                    <span class="text-xs text-gray-500"><?= date('M j', strtotime($conversation['last_message_time'])) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Messages Area -->
                <div class="w-full md:w-2/3 bg-white rounded-lg shadow-inner p-4 flex flex-col messages-area">
                    <?php if ($selected_contact): ?>
                        <!-- Chat Header -->
                        <div class="border-b pb-3 mb-4">
                            <div class="flex items-center">
                                <div class="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                    <?= substr(htmlspecialchars($selected_contact['full_name']), 0, 1) ?>
                                </div>
                                <div>
                                    <h2 class="font-semibold text-lg"><?= htmlspecialchars($selected_contact['full_name']) ?></h2>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($selected_contact['email']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Container -->
                        <div class="flex-1 overflow-y-auto space-y-4 mb-4" id="messages-container">
                            <?php if (empty($messages)): ?>
                                <p class="text-gray-500 text-center py-4">No messages yet. Start the conversation!</p>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php
                                    $is_outgoing = ($message['sender_type'] == $user_type);
                                    $message_time = date('M j, Y g:i A', strtotime($message['created_at']));
                                    ?>
                                    <div class="flex items-start <?= $is_outgoing ? 'justify-end' : '' ?>">
                                        <?php if (!$is_outgoing): ?>
                                            <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-3">
                                                <?= substr(htmlspecialchars($message['contact_name']), 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="<?= $is_outgoing ? 'message-outgoing rounded-lg rounded-tr-none' : 'message-incoming rounded-lg rounded-tl-none' ?> p-4 max-w-md">
                                                <p><?= htmlspecialchars($message['message']) ?></p>
                                            </div>
                                            <span class="text-xs text-gray-500 mt-1 block <?= $is_outgoing ? 'text-right' : '' ?>">
                                                <?= $message_time ?>
                                                <?php if ($is_outgoing && $message['is_read']): ?>
                                                    <i class="fas fa-check-double text-blue-500 ml-1"></i>
                                                <?php elseif ($is_outgoing): ?>
                                                    <i class="fas fa-check text-gray-400 ml-1"></i>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <?php if ($is_outgoing): ?>
                                            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold ml-3">
                                                <?= substr(htmlspecialchars($current_user_name), 0, 1) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Message Input -->
                        <div class="border-t pt-4">
                            <form method="POST">
                                <div class="flex items-center">
                                    <div class="flex-1 mr-2">
                                        <input type="text" name="message" placeholder="Type your message..." class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                                    </div>
                                    <button type="submit" name="send_message" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-lg transition">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-comments text-4xl mb-3"></i>
                                <p>Select a conversation to start messaging</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-700">Communication Guidelines</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-blue-500 text-2xl mb-2"><i class="fas fa-clock"></i></div>
                    <h3 class="font-semibold mb-2">Response Time</h3>
                    <p class="text-sm text-gray-600">Teachers typically respond within 24 hours on school days.</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-green-500 text-2xl mb-2"><i class="fas fa-info-circle"></i></div>
                    <h3 class="font-semibold mb-2">Be Specific</h3>
                    <p class="text-sm text-gray-600">Include your child's name and specific concern for faster assistance.</p>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-purple-500 text-2xl mb-2"><i class="fas fa-urgent"></i></div>
                    <h3 class="font-semibold mb-2">Urgent Matters</h3>
                    <p class="text-sm text-gray-600">For urgent issues, please contact the school office directly.</p>
                </div>
            </div>
        </div>
    </main>

    <?php
    include 'partials/footer.php';
    ?>
   

    <script>
        // Auto-scroll to the bottom of messages
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Simple search functionality
            const searchInput = document.querySelector('input[type="text"]');
            const conversationItems = document.querySelectorAll('.conversations-list > div > a');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                conversationItems.forEach(item => {
                    const name = item.querySelector('h3').textContent.toLowerCase();
                    const message = item.querySelector('p').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || message.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>