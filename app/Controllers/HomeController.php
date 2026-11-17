<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

class HomeController extends Controller {
    /**
     * Landing Page
     */
    public function index(Request $request, Response $response): void {
        $features = [
            [
                'icon' => 'bi-geo-alt-fill',
                'title' => 'Penjemputan Kurir & Live GPS',
                'desc' => 'Kurir siap menjemput laundry dari depan pintu rumah Anda dengan pelacakan live Maps.'
            ],
            [
                'icon' => 'bi-qr-code-scan',
                'title' => 'Pembayaran Fleksibel (Cash & QRIS)',
                'desc' => 'Bayar tunai di tempat atau online instan via QRIS dengan sistem timer otomatis 1 jam.'
            ],
            [
                'icon' => 'bi-robot',
                'title' => 'Asisten AI Pintar',
                'desc' => 'Tanyakan estimasi harga, konsultasi noda pakaian, dan lacak status laundry dengan AI Chatbot.'
            ],
            [
                'icon' => 'bi-shield-check',
                'title' => 'Mitra Toko Terpercaya',
                'desc' => 'Pilihan toko laundry terverifikasi dengan timbangan transparan dan pengerjaan higienis.'
            ]
        ];

        $popularServices = [
            ['name' => 'Laundry Kiloan (Cuci + Setrika)', 'price' => 7000, 'unit' => 'kg', 'badge' => 'Paling Populer', 'time' => '1-2 Hari'],
            ['name' => 'Laundry Express (6 Jam Selesai)', 'price' => 15000, 'unit' => 'kg', 'badge' => 'Super Cepat', 'time' => '6 Jam'],
            ['name' => 'Cuci Bed Cover / Selimut', 'price' => 25000, 'unit' => 'item', 'badge' => 'Satuan', 'time' => '1-2 Hari'],
            ['name' => 'Cuci Sepatu Premium & Helm', 'price' => 30000, 'unit' => 'pasang', 'badge' => 'Spesialis', 'time' => '2-3 Hari']
        ];

        $this->render('home/index', [
            'title' => 'SmartLaundry - Marketplace Laundry Berbasis Web & AI',
            'features' => $features,
            'popularServices' => $popularServices
        ], 'layouts/main');
    }

    /**
     * Stores explore list
     */
    public function stores(Request $request, Response $response): void {
        $storeModel = new \App\Models\Store();
        $stores = $storeModel->where(['is_active' => 1], 'rating_avg DESC, name ASC');

        $laundryServiceModel = new \App\Models\LaundryService();
        foreach ($stores as &$st) {
            $st['services'] = $laundryServiceModel->getByStoreId((int)$st['id']);
        }

        $this->render('home/stores', [
            'title' => 'Daftar Toko Laundry - SmartLaundry AI',
            'stores' => $stores
        ], 'layouts/main');
    }

    /**
     * About page
     */
    public function about(Request $request, Response $response): void {
        $this->render('home/about', [
            'title' => 'Tentang Platform - SmartLaundry AI'
        ], 'layouts/main');
    }
}
