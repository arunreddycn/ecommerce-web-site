<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch the user's cart items
$stmt = $conn->prepare("SELECT cart.*, products.name, products.price, products.image FROM cart 
                        JOIN products ON cart.product_id = products.id 
                        WHERE cart.user_id = ?");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_cost = 0;
foreach ($cart_items as $item) {
    $total_cost += $item['price'] * $item['quantity'];
}

// Handle Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $customer_name = $_POST['customer_name'];
    $customer_email = $_POST['customer_email'];
    $customer_phone = $_POST['customer_phone'];
    $customer_address = $_POST['customer_address'];
    $payment_method = $_POST['payment_method'];

    try {
        $conn->beginTransaction();

        // Insert into orders table
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_cost, customer_name, customer_email, customer_phone, customer_address, payment_method, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $total_cost, $customer_name, $customer_email, $customer_phone, $customer_address, $payment_method]);
        $order_id = $conn->lastInsertId();

        // Insert into order_items table
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($cart_items as $item) {
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        }

        // Clear the cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $conn->commit();

        // Redirect to success page
        header("Location: success.php?order_id=" . $order_id);
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout </title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Checkout</h1>
        </div>

        <div class="checkout-main">
            <div class="checkout-left">
                <div class="cart-summary">
                    <h3>Cart Summary</h3>
                    <div class="cart-items">
                        <?php foreach ($cart_items as $item) : ?>
                            <div class="cart-item">
                                <img src="../images/<?= $item['image']; ?>" alt="<?= $item['name']; ?>" class="cart-item-img">
                                <div class="cart-item-details">
                                    <p class="cart-item-name"><?= htmlspecialchars($item['name']); ?></p>
                                    <p>₹<?= number_format($item['price'], 2); ?> x <?= $item['quantity']; ?></p>
                                </div>
                                <div class="cart-item-total">
                                    ₹<?= number_format($item['price'] * $item['quantity'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-cost">
                        <h4>Total: ₹<?= number_format($total_cost, 2); ?></h4>
                    </div>
                </div>

                <div class="order-summary">
                    <h3>Order Summary</h3>
                    <div class="order-details">
                        <p>Shipping: Free</p>
                        <p>Tax: ₹0.00</p>
                        <div class="final-total">
                            <h4>Total Amount: ₹<?= number_format($total_cost, 2); ?></h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="checkout-right">
                <form method="POST">
                    <div class="billing-details">
                        <h3>Billing Details</h3>
                        <div class="form-group">
                            <label for="customer_name">Full Name</label>
                            <input type="text" name="customer_name" id="customer_name" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_email">Email</label>
                            <input type="email" name="customer_email" id="customer_email" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_phone">Phone Number</label>
                            <input type="text" name="customer_phone" id="customer_phone" required>
                        </div>
                        <div class="form-group">
                            <label for="customer_address">Shipping Address</label>
                            <textarea name="customer_address" id="customer_address" required></textarea>
                        </div>
                    </div>

                    <div class="payment-method">
                        <h3>Payment Method</h3>
                        <div class="form-group">
                            <input type="radio" name="payment_method" value="COD" id="payment_cod" checked>
                            <label for="payment_cod">Cash on Delivery</label>
                        </div>
                        <div class="form-group">
                            <input type="radio" name="payment_method" value="credit_card" id="payment_credit_card">
                            <label for="payment_credit_card">Credit Card / Debit Card</label>
                        </div>
                        <div class="form-group">
                            <input type="radio" name="payment_method" value="net_banking" id="payment_net_banking">
                            <label for="payment_net_banking">Net Banking</label>
                        </div>
                    </div>

                    <div class="checkout-action">
                        <button type="submit" name="checkout" class="checkout-button">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Basic Styling */
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7fb;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .checkout-container {
            width: 80%;
            max-width: 1200px;
            margin: 20px auto;
            background-color: white;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 30px;
        }
        .checkout-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .checkout-header h1 {
            font-size: 2.5em;
            color: #333;
        }

        .checkout-main {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .checkout-left, .checkout-right {
            width: 48%;
        }

        .cart-summary, .order-summary {
            margin-bottom: 30px;
        }

        .cart-items .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 10px;
        }

        .cart-item-img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        .cart-item-details {
            flex-grow: 1;
            padding-left: 10px;
        }

        .cart-item-total {
            font-weight: bold;
            color: #3d3d3d;
        }

        .total-cost, .final-total {
            font-weight: bold;
            font-size: 1.4em;
            color: #333;
            text-align: right;
        }

        .billing-details .form-group {
            margin-bottom: 20px;
        }

        .billing-details .form-group label {
            display: block;
            font-weight: bold;
        }

        .billing-details .form-group input,
        .billing-details .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .payment-method .form-group {
            margin-bottom: 15px;
        }

        .checkout-action {
            text-align: center;
        }

        .checkout-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 1.2em;
            border-radius: 5px;
            cursor: pointer;
        }

        .checkout-button:hover {
            background-color: #0056b3;
        }
    </style>
</body>
</html>

         