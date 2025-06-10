# Flutter Chat App Documentation

This documentation provides a comprehensive guide on how to create a Flutter chat application that connects to a Laravel backend with Reverb WebSocket server for real-time messaging.

## Table of Contents

1. [Project Overview](#project-overview)
2. [Prerequisites](#prerequisites)
3. [Flutter Project Setup](#flutter-project-setup)
4. [Authentication Implementation](#authentication-implementation)
5. [WebSocket Connection Setup](#websocket-connection-setup)
6. [UI Implementation](#ui-implementation)
7. [Message Handling](#message-handling)
8. [Testing and Deployment](#testing-and-deployment)

## Project Overview

This Flutter application will connect to a Laravel backend that uses Laravel Reverb for WebSocket communication. The app will allow users to:

- Authenticate with the Laravel backend
- View messages in real-time
- Send new messages
- Receive notifications when new messages arrive

## Prerequisites

Before starting, ensure you have the following:

- Flutter SDK installed (latest stable version)
- Dart SDK installed
- An IDE (VS Code, Android Studio, etc.)
- A running Laravel backend with Reverb WebSocket server
- Basic knowledge of Flutter and Dart
- Basic understanding of RESTful APIs and WebSockets

## Flutter Project Setup

### 1. Create a new Flutter project

```bash
flutter create flutter_chat_app
cd flutter_chat_app
```

### 2. Add required dependencies

Add the following dependencies to your `pubspec.yaml` file:

```yaml
dependencies:
  flutter:
    sdk: flutter
  cupertino_icons: ^1.0.2
  
  # HTTP requests
  http: ^1.1.0
  
  # State management
  provider: ^6.0.5
  
  # WebSocket connection
  web_socket_channel: ^2.4.0
  
  # Secure storage for tokens
  flutter_secure_storage: ^8.0.0
  
  # For handling JWT tokens
  jwt_decoder: ^2.0.1
  
  # For date formatting
  intl: ^0.18.1
```

### 3. Configure app permissions

#### For Android

Add the following permissions to your `android/app/src/main/AndroidManifest.xml` file:

```xml
<uses-permission android:name="android.permission.INTERNET"/>
```

#### For iOS

Add the following to your `ios/Runner/Info.plist` file:

```xml
<key>NSAppTransportSecurity</key>
<dict>
    <key>NSAllowsArbitraryLoads</key>
    <true/>
</dict>
```

### 4. Create the project structure

Create the following directory structure:

```
lib/
├── main.dart
├── config/
│   └── api_config.dart
├── models/
│   ├── user.dart
│   └── message.dart
├── providers/
│   ├── auth_provider.dart
│   └── message_provider.dart
├── screens/
│   ├── login_screen.dart
│   ├── register_screen.dart
│   └── chat_screen.dart
├── services/
│   ├── auth_service.dart
│   ├── message_service.dart
│   └── websocket_service.dart
└── widgets/
    ├── message_item.dart
    └── message_input.dart
```

## Authentication Implementation

### 1. Create API configuration

Create `lib/config/api_config.dart`:

```dart
class ApiConfig {
  static const String baseUrl = 'http://your-laravel-backend.com';
  static const String loginEndpoint = '/login';
  static const String registerEndpoint = '/register';
  static const String messagesEndpoint = '/messages';
  static const String messageEndpoint = '/message';
  
  // WebSocket configuration
  static const String wsUrl = 'ws://your-laravel-backend.com/reverb';
  static const String wsKey = 'your-reverb-app-key';
  static const String wsChannel = 'channel_for_everyone';
}
```

### 2. Create User model

Create `lib/models/user.dart`:

```dart
class User {
  final int id;
  final String name;
  final String email;

  User({
    required this.id,
    required this.name,
    required this.email,
  });

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'],
      name: json['name'],
      email: json['email'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
    };
  }
}
```

### 3. Create Authentication Service

Create `lib/services/auth_service.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../config/api_config.dart';
import '../models/user.dart';

class AuthService {
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  
  // Login user
  Future<User> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}${ApiConfig.loginEndpoint}'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'email': email,
        'password': password,
      }),
    );

    if (response.statusCode == 200) {
      final data = jsonDecode(response.body);
      
      // Save token
      await _storage.write(key: 'auth_token', value: data['token']);
      
      // Save user data
      final user = User.fromJson(data['user']);
      await _storage.write(key: 'user', value: jsonEncode(user.toJson()));
      
      return user;
    } else {
      throw Exception('Failed to login: ${response.body}');
    }
  }
  
  // Register user
  Future<User> register(String name, String email, String password) async {
    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}${ApiConfig.registerEndpoint}'),
      headers: {'Content-Type': 'application/json'},
      body: jsonEncode({
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': password,
      }),
    );

    if (response.statusCode == 201) {
      final data = jsonDecode(response.body);
      
      // Save token
      await _storage.write(key: 'auth_token', value: data['token']);
      
      // Save user data
      final user = User.fromJson(data['user']);
      await _storage.write(key: 'user', value: jsonEncode(user.toJson()));
      
      return user;
    } else {
      throw Exception('Failed to register: ${response.body}');
    }
  }
  
  // Get current user
  Future<User?> getCurrentUser() async {
    final userJson = await _storage.read(key: 'user');
    if (userJson != null) {
      return User.fromJson(jsonDecode(userJson));
    }
    return null;
  }
  
  // Get auth token
  Future<String?> getToken() async {
    return await _storage.read(key: 'auth_token');
  }
  
  // Logout
  Future<void> logout() async {
    await _storage.delete(key: 'auth_token');
    await _storage.delete(key: 'user');
  }
  
  // Check if user is authenticated
  Future<bool> isAuthenticated() async {
    final token = await getToken();
    return token != null;
  }
}
```

### 4. Create Authentication Provider

Create `lib/providers/auth_provider.dart`:

```dart
import 'package:flutter/foundation.dart';
import '../models/user.dart';
import '../services/auth_service.dart';

class AuthProvider with ChangeNotifier {
  final AuthService _authService = AuthService();
  User? _user;
  bool _isLoading = false;
  String? _error;

  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  // Initialize provider
  Future<void> initialize() async {
    _isLoading = true;
    notifyListeners();
    
    try {
      _user = await _authService.getCurrentUser();
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
  
  // Login
  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    
    try {
      _user = await _authService.login(email, password);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }
  
  // Register
  Future<bool> register(String name, String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    
    try {
      _user = await _authService.register(name, email, password);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }
  
  // Logout
  Future<void> logout() async {
    await _authService.logout();
    _user = null;
    notifyListeners();
  }
  
  // Check if user is authenticated
  Future<bool> isAuthenticated() async {
    return await _authService.isAuthenticated();
  }
}
```

## WebSocket Connection Setup

### 1. Create Message model

Create `lib/models/message.dart`:

```dart
class Message {
  final int id;
  final int userId;
  final String text;
  final String time;
  final Map<String, dynamic>? user;

  Message({
    required this.id,
    required this.userId,
    required this.text,
    required this.time,
    this.user,
  });

  factory Message.fromJson(Map<String, dynamic> json) {
    return Message(
      id: json['id'],
      userId: json['user_id'],
      text: json['text'],
      time: json['time'],
      user: json['user'],
    );
  }
}
```

### 2. Create WebSocket Service

Create `lib/services/websocket_service.dart`:

```dart
import 'dart:convert';
import 'package:web_socket_channel/web_socket_channel.dart';
import '../config/api_config.dart';

class WebSocketService {
  WebSocketChannel? _channel;
  Function(dynamic)? _onMessageCallback;
  
  // Connect to WebSocket
  void connect(String token) {
    final uri = Uri.parse('${ApiConfig.wsUrl}?token=$token');
    _channel = WebSocketChannel.connect(uri);
    
    // Listen for messages
    _channel!.stream.listen((message) {
      final data = jsonDecode(message);
      if (_onMessageCallback != null) {
        _onMessageCallback!(data);
      }
    });
    
    // Subscribe to private channel
    _subscribeToChannel();
  }
  
  // Subscribe to channel
  void _subscribeToChannel() {
    final subscriptionMessage = {
      'event': 'pusher:subscribe',
      'data': {
        'channel': 'private-${ApiConfig.wsChannel}',
      }
    };
    
    _channel?.sink.add(jsonEncode(subscriptionMessage));
  }
  
  // Set message callback
  void setOnMessageCallback(Function(dynamic) callback) {
    _onMessageCallback = callback;
  }
  
  // Disconnect from WebSocket
  void disconnect() {
    _channel?.sink.close();
    _channel = null;
  }
  
  // Check if connected
  bool isConnected() {
    return _channel != null;
  }
}
```

### 3. Create Message Service

Create `lib/services/message_service.dart`:

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../models/message.dart';
import 'auth_service.dart';

class MessageService {
  final AuthService _authService = AuthService();
  
  // Get all messages
  Future<List<Message>> getMessages() async {
    final token = await _authService.getToken();
    
    final response = await http.get(
      Uri.parse('${ApiConfig.baseUrl}${ApiConfig.messagesEndpoint}'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
    );

    if (response.statusCode == 200) {
      final List<dynamic> data = jsonDecode(response.body);
      return data.map((json) => Message.fromJson(json)).toList();
    } else {
      throw Exception('Failed to load messages: ${response.body}');
    }
  }
  
  // Send a message
  Future<void> sendMessage(String text) async {
    final token = await _authService.getToken();
    
    final response = await http.post(
      Uri.parse('${ApiConfig.baseUrl}${ApiConfig.messageEndpoint}'),
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer $token',
      },
      body: jsonEncode({
        'text': text,
      }),
    );

    if (response.statusCode != 200) {
      throw Exception('Failed to send message: ${response.body}');
    }
  }
}
```

### 4. Create Message Provider

Create `lib/providers/message_provider.dart`:

```dart
import 'package:flutter/foundation.dart';
import '../models/message.dart';
import '../services/message_service.dart';
import '../services/websocket_service.dart';
import '../services/auth_service.dart';

class MessageProvider with ChangeNotifier {
  final MessageService _messageService = MessageService();
  final WebSocketService _webSocketService = WebSocketService();
  final AuthService _authService = AuthService();
  
  List<Message> _messages = [];
  bool _isLoading = false;
  String? _error;

  List<Message> get messages => _messages;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  // Initialize provider
  Future<void> initialize() async {
    _isLoading = true;
    notifyListeners();
    
    try {
      // Get messages
      _messages = await _messageService.getMessages();
      
      // Connect to WebSocket
      final token = await _authService.getToken();
      if (token != null) {
        _webSocketService.connect(token);
        _webSocketService.setOnMessageCallback(_handleWebSocketMessage);
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
  
  // Handle WebSocket message
  void _handleWebSocketMessage(dynamic data) {
    if (data['event'] == 'GotMessage') {
      // Refresh messages when a new message is received
      _fetchMessages();
    }
  }
  
  // Fetch messages
  Future<void> _fetchMessages() async {
    try {
      _messages = await _messageService.getMessages();
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }
  
  // Send a message
  Future<void> sendMessage(String text) async {
    try {
      await _messageService.sendMessage(text);
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }
  
  // Disconnect WebSocket
  void disconnect() {
    _webSocketService.disconnect();
  }
  
  @override
  void dispose() {
    disconnect();
    super.dispose();
  }
}
```

## UI Implementation

### 1. Create Login Screen

Create `lib/screens/login_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import 'chat_screen.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({Key? key}) : super(key: key);

  @override
  _LoginScreenState createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    if (_formKey.currentState!.validate()) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final success = await authProvider.login(
        _emailController.text,
        _passwordController.text,
      );

      if (success && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const ChatScreen()),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Login'),
      ),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          return Padding(
            padding: const EdgeInsets.all(16.0),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  TextFormField(
                    controller: _emailController,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.emailAddress,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your email';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _passwordController,
                    decoration: const InputDecoration(
                      labelText: 'Password',
                      border: OutlineInputBorder(),
                    ),
                    obscureText: true,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your password';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),
                  if (authProvider.isLoading)
                    const CircularProgressIndicator()
                  else
                    ElevatedButton(
                      onPressed: _login,
                      child: const Text('Login'),
                    ),
                  if (authProvider.error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 16),
                      child: Text(
                        authProvider.error!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                  TextButton(
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const RegisterScreen(),
                        ),
                      );
                    },
                    child: const Text('Don\'t have an account? Register'),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
```

### 2. Create Register Screen

Create `lib/screens/register_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import 'chat_screen.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({Key? key}) : super(key: key);

  @override
  _RegisterScreenState createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _register() async {
    if (_formKey.currentState!.validate()) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final success = await authProvider.register(
        _nameController.text,
        _emailController.text,
        _passwordController.text,
      );

      if (success && mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const ChatScreen()),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Register'),
      ),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          return Padding(
            padding: const EdgeInsets.all(16.0),
            child: Form(
              key: _formKey,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(
                      labelText: 'Name',
                      border: OutlineInputBorder(),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your name';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _emailController,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      border: OutlineInputBorder(),
                    ),
                    keyboardType: TextInputType.emailAddress,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your email';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _passwordController,
                    decoration: const InputDecoration(
                      labelText: 'Password',
                      border: OutlineInputBorder(),
                    ),
                    obscureText: true,
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Please enter your password';
                      }
                      if (value.length < 8) {
                        return 'Password must be at least 8 characters';
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),
                  if (authProvider.isLoading)
                    const CircularProgressIndicator()
                  else
                    ElevatedButton(
                      onPressed: _register,
                      child: const Text('Register'),
                    ),
                  if (authProvider.error != null)
                    Padding(
                      padding: const EdgeInsets.only(top: 16),
                      child: Text(
                        authProvider.error!,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
```

### 3. Create Message Item Widget

Create `lib/widgets/message_item.dart`:

```dart
import 'package:flutter/material.dart';
import '../models/message.dart';
import '../models/user.dart';

class MessageItem extends StatelessWidget {
  final Message message;
  final User currentUser;

  const MessageItem({
    Key? key,
    required this.message,
    required this.currentUser,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final isCurrentUser = message.userId == currentUser.id;
    
    return Align(
      alignment: isCurrentUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4, horizontal: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isCurrentUser ? Colors.blue : Colors.grey[300],
          borderRadius: BorderRadius.circular(16),
        ),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.7,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!isCurrentUser && message.user != null)
              Text(
                message.user!['name'],
                style: TextStyle(
                  fontWeight: FontWeight.bold,
                  color: isCurrentUser ? Colors.white : Colors.black,
                ),
              ),
            Text(
              message.text,
              style: TextStyle(
                color: isCurrentUser ? Colors.white : Colors.black,
              ),
            ),
            Align(
              alignment: Alignment.bottomRight,
              child: Text(
                message.time,
                style: TextStyle(
                  fontSize: 10,
                  color: isCurrentUser ? Colors.white70 : Colors.black54,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

### 4. Create Message Input Widget

Create `lib/widgets/message_input.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/message_provider.dart';

class MessageInput extends StatefulWidget {
  const MessageInput({Key? key}) : super(key: key);

  @override
  _MessageInputState createState() => _MessageInputState();
}

class _MessageInputState extends State<MessageInput> {
  final _textController = TextEditingController();

  @override
  void dispose() {
    _textController.dispose();
    super.dispose();
  }

  void _sendMessage() {
    if (_textController.text.trim().isNotEmpty) {
      Provider.of<MessageProvider>(context, listen: false)
          .sendMessage(_textController.text);
      _textController.clear();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8.0),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              controller: _textController,
              decoration: const InputDecoration(
                hintText: 'Type a message...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.all(Radius.circular(24.0)),
                ),
                contentPadding: EdgeInsets.symmetric(horizontal: 16.0),
              ),
              onSubmitted: (_) => _sendMessage(),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.send),
            onPressed: _sendMessage,
          ),
        ],
      ),
    );
  }
}
```

### 5. Create Chat Screen

Create `lib/screens/chat_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/message_provider.dart';
import '../widgets/message_item.dart';
import '../widgets/message_input.dart';
import 'login_screen.dart';

class ChatScreen extends StatefulWidget {
  const ChatScreen({Key? key}) : super(key: key);

  @override
  _ChatScreenState createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<MessageProvider>(context, listen: false).initialize();
    });
  }

  void _scrollToBottom() {
    if (_scrollController.hasClients) {
      _scrollController.animateTo(
        _scrollController.position.maxScrollExtent,
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
      );
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Chat'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () async {
              await Provider.of<AuthProvider>(context, listen: false).logout();
              Provider.of<MessageProvider>(context, listen: false).disconnect();
              if (mounted) {
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
              }
            },
          ),
        ],
      ),
      body: Consumer2<AuthProvider, MessageProvider>(
        builder: (context, authProvider, messageProvider, _) {
          if (authProvider.isLoading || messageProvider.isLoading) {
            return const Center(child: CircularProgressIndicator());
          }

          if (authProvider.user == null) {
            return const Center(
              child: Text('You need to login first'),
            );
          }

          WidgetsBinding.instance.addPostFrameCallback((_) => _scrollToBottom());

          return Column(
            children: [
              Expanded(
                child: messageProvider.messages.isEmpty
                    ? const Center(child: Text('No messages yet'))
                    : ListView.builder(
                        controller: _scrollController,
                        itemCount: messageProvider.messages.length,
                        itemBuilder: (context, index) {
                          return MessageItem(
                            message: messageProvider.messages[index],
                            currentUser: authProvider.user!,
                          );
                        },
                      ),
              ),
              const MessageInput(),
            ],
          );
        },
      ),
    );
  }
}
```

### 6. Update Main App

Update `lib/main.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'providers/auth_provider.dart';
import 'providers/message_provider.dart';
import 'screens/login_screen.dart';
import 'screens/chat_screen.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => MessageProvider()),
      ],
      child: MaterialApp(
        title: 'Flutter Chat App',
        theme: ThemeData(
          primarySwatch: Colors.blue,
          visualDensity: VisualDensity.adaptivePlatformDensity,
        ),
        home: FutureBuilder(
          future: Provider.of<AuthProvider>(context, listen: false).initialize(),
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Scaffold(
                body: Center(child: CircularProgressIndicator()),
              );
            }
            
            return Consumer<AuthProvider>(
              builder: (context, authProvider, _) {
                if (authProvider.user != null) {
                  return const ChatScreen();
                }
                return const LoginScreen();
              },
            );
          },
        ),
      ),
    );
  }
}
```

## Message Handling

### 1. Handling Real-time Messages

The WebSocket connection is established in the `MessageProvider` class, which listens for `GotMessage` events from the Laravel backend. When a new message is received, the provider fetches the latest messages from the server and updates the UI.

### 2. Sending Messages

Messages are sent using the `MessageService.sendMessage` method, which makes a POST request to the Laravel backend. The backend then broadcasts the message to all connected clients via WebSockets.

### 3. Displaying Messages

Messages are displayed in the `ChatScreen` using the `MessageItem` widget, which shows the sender's name, message text, and timestamp. Messages from the current user are displayed on the right side of the screen, while messages from other users are displayed on the left.

## Testing and Deployment

### 1. Testing the App

Before deploying, test the app thoroughly:

1. Test authentication (login and registration)
2. Test sending and receiving messages
3. Test WebSocket connection and real-time updates
4. Test error handling and edge cases

### 2. Deployment

To deploy the app:

1. Configure the app for production:
   - Update the API URLs to point to your production server
   - Enable ProGuard for Android to optimize the app
   - Configure signing for both Android and iOS

2. Build the app for distribution:
   ```bash
   # For Android
   flutter build appbundle
   
   # For iOS
   flutter build ios
   ```

3. Publish to app stores:
   - Follow the Google Play Console and Apple App Store guidelines for app submission

## Conclusion

This documentation provides a comprehensive guide for creating a Flutter chat application that connects to a Laravel backend with Reverb WebSocket server. By following these steps, you can create a fully functional real-time chat application with authentication, message sending and receiving, and real-time updates.

Remember to update the API URLs and WebSocket configuration to match your Laravel backend setup. You may also need to adjust the authentication flow based on your specific requirements.
