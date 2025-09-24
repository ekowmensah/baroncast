<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USSD Payment Test - E-Cast Voting</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #666;
        }
        
        .test-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-right: 1rem;
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .ussd-demo {
            background: #e8f5e8;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            display: none;
        }
        
        .ussd-demo.show {
            display: block;
        }
        
        .ussd-code {
            font-size: 2.5rem;
            font-weight: bold;
            color: #28a745;
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 2px dashed #28a745;
        }
        
        .instructions {
            text-align: left;
            margin: 1.5rem 0;
        }
        
        .instructions ol {
            padding-left: 2rem;
        }
        
        .instructions li {
            margin: 0.5rem 0;
            font-size: 1rem;
        }
        
        .status {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
        }
        
        .status.pending {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
        
        .status.success {
            background: #d4edda;
            border: 1px solid #28a745;
            color: #155724;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .feature {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .feature i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .feature h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .feature p {
            color: #666;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-mobile-alt"></i> USSD Payment Integration Test</h1>
            <p>Test the Arkesel USSD payment system for E-Cast Voting Platform</p>
        </div>
        
        <div class="test-form">
            <h3>Simulate USSD Payment</h3>
            <form id="testForm">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" id="phoneNumber" class="form-input" placeholder="0245152060" value="0245152060">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Amount (GHS)</label>
                    <input type="number" id="amount" class="form-input" placeholder="5.00" value="5.00" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Network</label>
                    <select id="network" class="form-select">
                        <option value="MTN">MTN Mobile Money</option>
                        <option value="Vodafone">Vodafone Cash</option>
                        <option value="AirtelTigo">AirtelTigo Money</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-play"></i> Generate USSD Code
                </button>
            </form>
        </div>
        
        <div class="ussd-demo" id="ussdDemo">
            <h3><i class="fas fa-mobile-alt"></i> Complete Payment via USSD</h3>
            <p>Dial the code below on your mobile phone to complete payment:</p>
            
            <div class="ussd-code" id="ussdCode">*170*456*500#</div>
            
            <div class="instructions">
                <h4>Instructions:</h4>
                <ol>
                    <li>Dial the USSD code above on your mobile phone</li>
                    <li>Follow the prompts on your screen</li>
                    <li>Confirm payment of GHS <span id="paymentAmount">5.00</span></li>
                    <li>Enter your Mobile Money PIN</li>
                    <li>Payment confirmation will be received automatically</li>
                </ol>
                <p><strong>Note:</strong> This works with MTN Mobile Money, Vodafone Cash, and AirtelTigo Money</p>
            </div>
            
            <div class="status pending" id="paymentStatus">
                <i class="fas fa-clock"></i> Waiting for payment confirmation...
            </div>
            
            <button onclick="simulateSuccess()" class="btn" style="background: #28a745;">
                <i class="fas fa-check"></i> Simulate Success
            </button>
            
            <button onclick="resetDemo()" class="btn" style="background: #6c757d;">
                <i class="fas fa-redo"></i> Reset Demo
            </button>
        </div>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-shield-alt"></i>
                <h3>Secure Payments</h3>
                <p>All payments are processed securely through mobile money providers with PIN authentication</p>
            </div>
            
            <div class="feature">
                <i class="fas fa-mobile-alt"></i>
                <h3>USSD Compatible</h3>
                <p>Works on any mobile phone - no smartphone or internet connection required</p>
            </div>
            
            <div class="feature">
                <i class="fas fa-bolt"></i>
                <h3>Instant Processing</h3>
                <p>Real-time payment confirmation and automatic vote recording</p>
            </div>
            
            <div class="feature">
                <i class="fas fa-network-wired"></i>
                <h3>Multi-Network Support</h3>
                <p>Supports MTN, Vodafone, and AirtelTigo mobile money services</p>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('testForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const phoneNumber = document.getElementById('phoneNumber').value;
            const amount = parseFloat(document.getElementById('amount').value);
            const network = document.getElementById('network').value;
            
            // Generate USSD code based on amount
            const amountCode = Math.round(amount * 100); // Convert to pesewas
            const ussdCode = `*170*456*${amountCode}#`;
            
            // Update demo
            document.getElementById('ussdCode').textContent = ussdCode;
            document.getElementById('paymentAmount').textContent = amount.toFixed(2);
            document.getElementById('ussdDemo').classList.add('show');
            
            // Reset status
            const status = document.getElementById('paymentStatus');
            status.className = 'status pending';
            status.innerHTML = '<i class="fas fa-clock"></i> Waiting for payment confirmation...';
        });
        
        function simulateSuccess() {
            const status = document.getElementById('paymentStatus');
            status.className = 'status success';
            status.innerHTML = '<i class="fas fa-check-circle"></i> Payment successful! Vote would be recorded automatically.';
        }
        
        function resetDemo() {
            document.getElementById('ussdDemo').classList.remove('show');
            document.getElementById('testForm').reset();
            document.getElementById('amount').value = '5.00';
            document.getElementById('phoneNumber').value = '0245152060';
        }
    </script>
</body>
</html>
