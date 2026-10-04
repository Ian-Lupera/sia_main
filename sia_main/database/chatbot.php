<?php
/*
|--------------------------------------------------------------------------
| chatbot.php
|--------------------------------------------------------------------------
| Shared AI chatbot for buyer.php and farmer.php
|--------------------------------------------------------------------------
*/

session_start();

header("Content-Type: application/json");

// ============================================================
// DATABASE CONNECTION
// Change these to match your database
// ============================================================

$host = "localhost";
$dbname = "market";
$username = "root";
$password = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database connection failed."
    ]);
    exit;
}


// ============================================================
// GET USER INFORMATION
// ============================================================

$userId = $_SESSION["user_id"] ?? null;
$userType = $_SESSION["role"] ?? null;

if (!$userId) {
    echo json_encode([
        "success" => false,
        "message" => "Please log in first."
    ]);
    exit;
}


// ============================================================
// GET QUESTION
// ============================================================

$input = json_decode(file_get_contents("php://input"), true);

$question = trim($input["message"] ?? "");

if ($question === "") {
    echo json_encode([
        "success" => false,
        "message" => "Please enter a question."
    ]);
    exit;
}


// ============================================================
// GET PRODUCTS (public: only available products)
// ============================================================

try {

    $stmt = $pdo->query("
        SELECT
            product_id AS id,
            product_name AS name,
            variety,
            unit,
            description,
            price,
            quantity AS stock
        FROM products
        WHERE status = 'available'
        ORDER BY product_name ASC
    ");

    $products = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log("chatbot products: " . $e->getMessage());
    $products = [];
}


// ============================================================
// GET USER'S ORDERS (only rows that belong to this user)
// ============================================================

$orders = [];

try {

    if ($userType === "buyer") {

        $stmt = $pdo->prepare("
            SELECT
                t.transaction_id AS id,
                p.product_name,
                t.quantity,
                t.total_amount,
                t.status,
                t.transaction_date AS created_at
            FROM sale_transaction t
            JOIN products p ON p.product_id = t.product_id
            WHERE t.user_id = ?
            ORDER BY t.transaction_date DESC
            LIMIT 20
        ");

        $stmt->execute([$userId]);

        $orders = $stmt->fetchAll();

    } elseif ($userType === "farmer") {

        $stmt = $pdo->prepare("
            SELECT
                t.transaction_id AS id,
                p.product_name,
                t.quantity,
                t.total_amount,
                t.status,
                t.transaction_date AS created_at
            FROM sale_transaction t
            JOIN products p ON p.product_id = t.product_id
            WHERE p.user_id = ?
            ORDER BY t.transaction_date DESC
            LIMIT 20
        ");

        $stmt->execute([$userId]);

        $orders = $stmt->fetchAll();
    }

} catch (PDOException $e) {

    error_log("chatbot orders: " . $e->getMessage());
    $orders = [];
}


// ============================================================
// GET FARMER'S OWN PRODUCTS
// ============================================================

$farmerProducts = [];

if ($userType === "farmer") {

    try {

        $stmt = $pdo->prepare("
            SELECT
                product_id AS id,
                product_name AS name,
                variety,
                unit,
                description,
                price,
                quantity AS stock,
                reorder_level,
                status
            FROM products
            WHERE user_id = ?
            ORDER BY product_name ASC
        ");

        $stmt->execute([$userId]);

        $farmerProducts = $stmt->fetchAll();

    } catch (PDOException $e) {

        error_log("chatbot farmer products: " . $e->getMessage());
        $farmerProducts = [];
    }
}


// ============================================================
// PREPARE DATABASE INFORMATION
// ============================================================

$databaseInformation = [

    "user_type" => $userType,

    "available_products" => $products,

    "user_orders" => $orders,

    "farmer_products" => $farmerProducts

];

$databaseJson = json_encode(
    $databaseInformation,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
);


// ============================================================
// GEMINI API
// ============================================================

// IMPORTANT:
// Put your NEW Gemini API key here temporarily for testing,
// or preferably load it from an environment variable.

if (file_exists(__DIR__ . "/config.php")) {
    require __DIR__ . "/config.php";   // contains: putenv("GEMINI_API_KEY=your-key");
}

$apiKey = "AQ.Ab8RN6J0EFUu4FasCViS9Zp-NyQJazzqMPrj6zBXuUu885pYdQ";   // <-- paste your Gemini key between these quotes (keep the semicolon)

if ($apiKey === "") {
    $apiKey = getenv("AQ.Ab8RN6J0EFUu4FasCViS9Zp-NyQJazzqMPrj6zBXuUu885pYdQ");   // or load it from database/config.php
}

if (!$apiKey) {

    echo json_encode([
        "success" => false,
        "message" => "Gemini API key is not configured on the server."
    ]);

    exit;
}


// ============================================================
// SYSTEM INSTRUCTIONS
// ============================================================

$systemInstruction = <<<TEXT

You are the official AI assistant for a farm marketplace website.

The website connects buyers and farmers.

The current user is a {$userType}.

Your job is to answer questions about this website using the database information provided below.

IMPORTANT RULES:

1. Never invent product prices.
2. Never invent product stock.
3. Never invent order information.
4. Never invent farmer information.
5. Use the database information whenever the question requires real system data.
6. If information is not available in the database, clearly say that the information is not available.
7. Do not reveal private information belonging to another user.
8. Buyers can receive information about publicly available products.
9. Farmers can receive information about their own products, inventory, orders and sales.
10. The website currently accepts CASH ON DELIVERY (COD) only.
11. Do not tell users that online payment is available.
12. Keep answers simple and easy to understand.
13. You can answer general questions about how the website works.
14. If the user asks something unrelated to the farm marketplace, politely explain that you can mainly help with this website.
15. Do not make up information just to satisfy the user.

DATABASE INFORMATION:

{$databaseJson}

TEXT;


// ============================================================
// GEMINI REQUEST
// ============================================================

$model = "gemini-3.5-flash";

$url = "https://generativelanguage.googleapis.com/v1beta/models/" . $model . ":generateContent";


$requestData = [

    "system_instruction" => [

        "parts" => [

            [
                "text" => $systemInstruction
            ]

        ]

    ],

    "contents" => [

        [
            "role" => "user",

            "parts" => [

                [
                    "text" => $question
                ]

            ]

        ]

    ],

    "generationConfig" => [

        "temperature" => 0.2,

        "maxOutputTokens" => 1024

    ]

];


// ============================================================
// CURL REQUEST
// ============================================================

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-goog-api-key: " . $apiKey
]);

curl_setopt($ch, CURLOPT_TIMEOUT, 30);

curl_setopt(
    $ch,
    CURLOPT_POSTFIELDS,
    json_encode($requestData)
);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$curlError = curl_error($ch);

curl_close($ch);


// ============================================================
// HANDLE GEMINI ERROR
// ============================================================

if ($response === false || $httpCode >= 400) {

    error_log("Gemini error (HTTP $httpCode): " . ($response === false ? $curlError : $response));

    echo json_encode([
        "success" => false,
        "message" => "The AI service is currently unavailable."
    ]);

    exit;
}


// ============================================================
// READ GEMINI RESPONSE
// ============================================================

$result = json_decode($response, true);

$answer =
    $result["candidates"][0]["content"]["parts"][0]["text"]
    ?? "Sorry, I could not generate an answer.";

echo json_encode([

    "success" => true,

    "answer" => $answer

]);

exit;
?>