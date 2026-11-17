<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Models\Store;
use App\Models\Setting;
use App\Services\WhatsAppNotificationService;

class StoreExpenseController extends Controller {
    private Store $storeModel;

    public function __construct() {
        $this->storeModel = new Store();
    }

    /**
     * Display expenses list
     * GET /store/expenses
     */
    public function index(Request $request, Response $response): void {
        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store) {
            set_flash('error', 'Toko tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        // Check if admin_toko is allowed to view finance
        if ($user['role'] === 'admin_toko') {
            $canView = (bool)Setting::getStore((int)$store['id'], 'admin_toko_can_view_finance', Setting::get('admin_toko_can_view_finance', false));
            if (!$canView) {
                set_flash('error', 'Akses dibatasi: Admin toko tidak memiliki izin melihat data keuangan.');
                $this->redirect('dashboard/store');
                return;
            }
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT se.*, u.name as recorded_by_name 
            FROM `store_expenses` se 
            LEFT JOIN `users` u ON se.recorded_by = u.id 
            WHERE se.store_id = :sid 
            ORDER BY se.expense_date DESC, se.id DESC LIMIT 100");
        $stmt->execute(['sid' => (int)$store['id']]);
        $expenses = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->render('store/expenses/index', [
            'title' => 'Pengeluaran Toko: ' . $store['name'] . ' - ' . APP_NAME,
            'user' => $user,
            'store' => $store,
            'expenses' => $expenses
        ], 'layouts/main');
    }

    /**
     * Store a newly recorded expense
     * POST /store/expenses
     */
    public function store(Request $request, Response $response): void {
        $csrf = $request->get('_csrf_token');
        if (!verify_csrf_token($csrf)) {
            set_flash('error', 'Token CSRF tidak valid.');
            $this->redirect('store/expenses');
            return;
        }

        $user = auth_user();
        $store = $this->storeModel->findForUser((int)$user['id'], $user['role']);
        if (!$store) {
            set_flash('error', 'Toko tidak ditemukan.');
            $this->redirect('dashboard/store');
            return;
        }

        $itemName = trim((string)$request->get('item_name', ''));
        $category = strtoupper(trim((string)$request->get('category', 'OPERASIONAL')));
        $amount = (float)str_replace(['.', ','], '', (string)$request->get('amount', '0'));
        $expenseDate = trim((string)$request->get('expense_date', date('Y-m-d H:i:s')));
        if (strlen($expenseDate) === 10) {
            $expenseDate .= ' ' . date('H:i:s');
        }
        $supplier = trim((string)$request->get('supplier', ''));
        $notes = trim((string)$request->get('notes', ''));

        if (empty($itemName) || $amount <= 0) {
            set_flash('error', 'Nama barang dan nominal pengeluaran wajib diisi.');
            $this->redirect('store/expenses');
            return;
        }

        $validCats = ['BAHAN_BAKU', 'KEMASAN', 'OPERASIONAL', 'LAINNYA', 'PERALATAN', 'UTILITAS', 'GAJI'];
        if (!in_array($category, $validCats, true)) {
            $category = 'OPERASIONAL';
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO `store_expenses` 
            (`store_id`, `recorded_by`, `title`, `category`, `item_name`, `quantity`, `unit`, `unit_price`, `amount`, `total_amount`, `supplier`, `expense_date`, `notes`, `created_at`) 
            VALUES (:sid, :rec_by, :title, :cat, :item, 1, 'pcs', :amt, :amt, :amt, :supp, :dt, :notes, NOW())");
        $stmt->execute([
            'sid' => (int)$store['id'],
            'rec_by' => (int)$user['id'],
            'title' => $itemName,
            'cat' => $category,
            'item' => $itemName,
            'amt' => $amount,
            'supp' => !empty($supplier) ? $supplier : null,
            'dt' => $expenseDate,
            'notes' => !empty($notes) ? $notes : null
        ]);
        $expenseId = (int)$db->lastInsertId();

        // Trigger WhatsApp Notification
        $waService = new WhatsAppNotificationService();
        $waService->notifyStoreExpenseRecorded(
            (int)$store['id'],
            $category,
            $itemName,
            $amount,
            $expenseDate,
            $expenseId
        );

        set_flash('success', "Pengeluaran \"{$itemName}\" sebesar " . format_rupiah($amount) . " berhasil dicatat.");
        $this->redirect('store/expenses');
    }
}

