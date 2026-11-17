<?php
$file = "c:/xampp/htdocs/RPL2/app/Services/AIChatbotService.php";
$code = file_get_contents($file);

$code = str_replace(
    "public function chat(string \$message, int \$userId, ?int \$orderId = null): array {",
    "public function chat(string \$message, int \$userId, string \$role = \"customer\", ?int \$orderId = null): array {",
    $code
);

$code = str_replace(
    "\$intent = \$this->detectIntent(\$cleanMessage);",
    "\$intent = \$this->detectIntent(\$cleanMessage, \$role);",
    $code
);

$code = str_replace(
    "\$result = \$this->handleMockResponse(\$cleanMessage, \$intent, \$userId, \$orderId);",
    "\$result = \$this->handleMockResponse(\$cleanMessage, \$intent, \$userId, \$role, \$orderId);",
    $code
);

$code = str_replace(
    "\$reply = \$this->callRealAIProvider(\$cleanMessage, \$intent, \$userId, \$orderId);",
    "\$reply = \$this->callRealAIProvider(\$cleanMessage, \$intent, \$userId, \$role, \$orderId);",
    $code
);

file_put_contents($file, $code);
echo "Patch applied.";

