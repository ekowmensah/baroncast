<?php
/**
 * Test Shortcode Voting Interface
 * Simulates USSD interactions for testing
 */

require_once __DIR__ . '/services/ShortcodeVotingService.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📱 Test Shortcode Voting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .phone-simulator {
            max-width: 400px;
            margin: 0 auto;
            background: #000;
            border-radius: 25px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .screen {
            background: #1a1a1a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            padding: 15px;
            border-radius: 10px;
            min-height: 400px;
            white-space: pre-wrap;
            overflow-y: auto;
        }
        .input-area {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        .input-area input {
            flex: 1;
            background: #333;
            color: white;
            border: 1px solid #555;
            border-radius: 5px;
            padding: 10px;
        }
        .input-area button {
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
        }
        .input-area button:hover {
            background: #218838;
        }
        .controls {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .session-info {
            background: rgba(255,255,255,0.1);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .quick-actions button {
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="session-info text-center">
            <h1><i class="fas fa-mobile-alt me-2"></i>Shortcode Voting Simulator</h1>
            <p>Test the USSD shortcode voting experience</p>
        </div>
        
        <div class="phone-simulator">
            <div class="screen" id="screen">
Welcome to USSD Simulator
Dial *170*123# to start voting...

Click "Start Session" below to begin.
            </div>
            
            <div class="input-area">
                <input type="text" id="userInput" placeholder="Enter your choice..." maxlength="50">
                <button onclick="sendInput()"><i class="fas fa-paper-plane"></i></button>
            </div>
            
            <div class="quick-actions">
                <button onclick="quickInput('1')">1</button>
                <button onclick="quickInput('2')">2</button>
                <button onclick="quickInput('3')">3</button>
                <button onclick="quickInput('0')">0</button>
                <button onclick="quickInput('*')">*</button>
                <button onclick="quickInput('#')">#</button>
            </div>
        </div>
        
        <div class="controls">
            <h4><i class="fas fa-cogs me-2"></i>Test Controls</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label">Phone Number:</label>
                    <input type="text" id="phoneNumber" class="form-control" value="233241234567" placeholder="233xxxxxxxxx">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Session ID:</label>
                    <input type="text" id="sessionId" class="form-control" value="" placeholder="Auto-generated">
                </div>
            </div>
            
            <div class="mt-3">
                <button class="btn btn-success me-2" onclick="startSession()">
                    <i class="fas fa-play me-1"></i>Start Session
                </button>
                <button class="btn btn-warning me-2" onclick="resetSession()">
                    <i class="fas fa-redo me-1"></i>Reset Session
                </button>
                <button class="btn btn-info me-2" onclick="showSessionData()">
                    <i class="fas fa-info me-1"></i>Session Data
                </button>
                <button class="btn btn-secondary" onclick="clearScreen()">
                    <i class="fas fa-eraser me-1"></i>Clear Screen
                </button>
            </div>
            
            <div class="mt-3">
                <h6>Quick Test Scenarios:</h6>
                <button class="btn btn-sm btn-outline-primary me-2" onclick="testScenario('complete_vote')">
                    Complete Vote Flow
                </button>
                <button class="btn btn-sm btn-outline-primary me-2" onclick="testScenario('cancel_vote')">
                    Cancel Vote
                </button>
                <button class="btn btn-sm btn-outline-primary" onclick="testScenario('invalid_input')">
                    Invalid Input
                </button>
            </div>
        </div>
        
        <div class="controls">
            <h4><i class="fas fa-chart-line me-2"></i>Session Statistics</h4>
            <div id="sessionStats">
                <p>No active session</p>
            </div>
        </div>
    </div>

    <script>
        let currentSessionId = null;
        let sessionStep = 'welcome';
        let interactionCount = 0;
        
        function generateSessionId() {
            return 'TEST_' + Math.random().toString(36).substr(2, 9);
        }
        
        function startSession() {
            const phoneNumber = document.getElementById('phoneNumber').value;
            if (!phoneNumber) {
                alert('Please enter a phone number');
                return;
            }
            
            currentSessionId = generateSessionId();
            document.getElementById('sessionId').value = currentSessionId;
            
            appendToScreen('\n--- Starting New Session ---');
            appendToScreen('Phone: ' + phoneNumber);
            appendToScreen('Session: ' + currentSessionId);
            appendToScreen('Dialing *170*123#...\n');
            
            sendRequest('', true);
        }
        
        function sendInput() {
            const input = document.getElementById('userInput').value;
            sendRequest(input);
            document.getElementById('userInput').value = '';
        }
        
        function quickInput(value) {
            document.getElementById('userInput').value = value;
            sendInput();
        }
        
        function sendRequest(input, isStart = false) {
            const phoneNumber = document.getElementById('phoneNumber').value;
            const sessionId = document.getElementById('sessionId').value || currentSessionId;
            
            if (!phoneNumber) {
                alert('Please enter a phone number');
                return;
            }
            
            if (!isStart) {
                appendToScreen('> ' + input);
            }
            
            // Show loading
            appendToScreen('Processing...');
            
            const requestData = {
                phone_number: phoneNumber,
                input: input,
                session_id: sessionId,
                sequence: interactionCount + 1
            };
            
            fetch('webhooks/shortcode-voting-webhook.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                // Remove loading message
                removeLastLine();
                
                if (data.Message) {
                    appendToScreen('\n' + data.Message);
                    
                    if (data.Type === 'Release') {
                        appendToScreen('\n--- Session Ended ---\n');
                        sessionStep = 'ended';
                    }
                } else {
                    appendToScreen('\nError: Invalid response from server');
                }
                
                interactionCount++;
                updateSessionStats();
            })
            .catch(error => {
                removeLastLine();
                appendToScreen('\nError: ' + error.message);
                console.error('Error:', error);
            });
        }
        
        function appendToScreen(text) {
            const screen = document.getElementById('screen');
            screen.textContent += text + '\n';
            screen.scrollTop = screen.scrollHeight;
        }
        
        function removeLastLine() {
            const screen = document.getElementById('screen');
            const lines = screen.textContent.split('\n');
            lines.pop(); // Remove last empty line
            lines.pop(); // Remove "Processing..." line
            screen.textContent = lines.join('\n') + '\n';
        }
        
        function clearScreen() {
            document.getElementById('screen').textContent = 'Screen cleared. Click "Start Session" to begin.\n';
        }
        
        function resetSession() {
            currentSessionId = null;
            sessionStep = 'welcome';
            interactionCount = 0;
            document.getElementById('sessionId').value = '';
            clearScreen();
            updateSessionStats();
        }
        
        function updateSessionStats() {
            const stats = document.getElementById('sessionStats');
            if (currentSessionId) {
                stats.innerHTML = `
                    <p><strong>Session ID:</strong> ${currentSessionId}</p>
                    <p><strong>Current Step:</strong> ${sessionStep}</p>
                    <p><strong>Interactions:</strong> ${interactionCount}</p>
                    <p><strong>Phone:</strong> ${document.getElementById('phoneNumber').value}</p>
                `;
            } else {
                stats.innerHTML = '<p>No active session</p>';
            }
        }
        
        function showSessionData() {
            if (!currentSessionId) {
                alert('No active session');
                return;
            }
            
            // This would typically fetch session data from the server
            alert('Session Data:\n' + 
                  'ID: ' + currentSessionId + '\n' +
                  'Step: ' + sessionStep + '\n' +
                  'Interactions: ' + interactionCount);
        }
        
        function testScenario(scenario) {
            switch(scenario) {
                case 'complete_vote':
                    alert('This would simulate a complete voting flow with automatic inputs');
                    break;
                case 'cancel_vote':
                    quickInput('0');
                    break;
                case 'invalid_input':
                    quickInput('999');
                    break;
            }
        }
        
        // Handle Enter key in input
        document.getElementById('userInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendInput();
            }
        });
        
        // Initialize
        updateSessionStats();
    </script>
</body>
</html>
