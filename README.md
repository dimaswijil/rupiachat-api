# 🚀 RupiaChat API - System Architecture & Documentation

Selamat datang di repositori backend **RupiaChat API**. Dokumen ini menjelaskan secara rinci tentang arsitektur sistem, pola desain, integrasi cloud, dan teknologi yang kita gunakan di seluruh ekosistem RupiaChat (Flutter Client & Laravel API Backend).

---

## 🏗️ Gambaran Arsitektur Sistem (System Architecture)

RupiaChat menggunakan arsitektur **Client-Server** modern dengan komunikasi berbasis **RESTful API** untuk transaksi data dan protokol **WebSockets / Event-Driven** untuk sinkronisasi waktu-nyata (*real-time*).

```mermaid
flowchart TD
    %% Styling Classes (Tailored to RupiaColors Theme)
    classDef clientClass fill:#EBF3FF,stroke:#1A3C8F,stroke-width:2.5px,color:#0A1E50,font-weight:bold;
    classDef backendClass fill:#FFF0F0,stroke:#993C1D,stroke-width:2.5px,color:#501400,font-weight:bold;
    classDef storageClass fill:#EBFDF5,stroke:#0F6E56,stroke-width:2.5px,color:#053520,font-weight:bold;
    classDef pusherClass fill:#FFF9E6,stroke:#F4A900,stroke-width:2.5px,color:#6B4600,font-weight:bold;
    classDef fcmClass fill:#FFF2EB,stroke:#E65100,stroke-width:2.5px,color:#5D2000,font-weight:bold;

    subgraph Client [📱 Flutter Client App Architecture]
        style Client fill:#FAFCFF,stroke:#1A3C8F,stroke-width:2px,stroke-dasharray: 4;
        F["🖥️ Flutter UI App<br>(Widgets & Layouts)"]:::clientClass
        F1["⚡ ValueNotifier State<br>(Reactive Core)"]:::clientClass
        F2["📦 Service Layer<br>(Dio Services)"]:::clientClass
    end

    subgraph Backend [💻 Laravel API Server Architecture]
        style Backend fill:#FFFAFA,stroke:#993C1D,stroke-width:2px,stroke-dasharray: 4;
        L1["⚙️ RESTful Controllers<br>(Modular API Routes)"]:::backendClass
        L2["🗄️ Eloquent ORM<br>(Database Models)"]:::backendClass
    end

    subgraph Cloud [☁️ Cloud & Database Infrastructure]
        style Cloud fill:#FAFFFB,stroke:#0F6E56,stroke-width:2px,stroke-dasharray: 4;
        T[("🗃️ TiDB Cloud Database<br>(ACID Transactions)")]:::storageClass
        S["📂 Supabase Cloud Storage<br>(Media Bucket Storage)"]:::storageClass
        P["📡 Pusher Channels Real-Time<br>(WebSockets Pub/Sub)"]:::pusherClass
        FCM["🔔 Firebase Cloud Messaging<br>(Heads-Up Push Gateway)"]:::fcmClass
    end

    %% Flow lines
    F -->|User Interaction| F1
    F1 --> F2
    F2 -->|HTTPS REST Request| L1
    L1 --> L2
    L2 -->|Read/Write SQL| T
    L1 -->|Uploads PDF/Audio| S
    L1 -->|Trigger Sync Event| P
    L1 -->|Trigger Heads-Up Call| FCM
    P -->|WebSocket Stream| F
    FCM -->|Push Notification| F

    %% Link styles for vibrant coloring
    linkStyle 0,1 stroke:#1A3C8F,stroke-width:2px;
    linkStyle 2 stroke:#1A3C8F,stroke-width:2.5px,stroke-dasharray: 5 5;
    linkStyle 3 stroke:#993C1D,stroke-width:2px;
    linkStyle 4 stroke:#F4A900,stroke-width:2.5px;
    linkStyle 5 stroke:#0F6E56,stroke-width:2px;
    linkStyle 6 stroke:#F4A900,stroke-width:2px;
    linkStyle 7 stroke:#E65100,stroke-width:2px;
    linkStyle 8 stroke:#0F6E56,stroke-width:2.5px,stroke-dasharray: 5 5;
    linkStyle 9 stroke:#E65100,stroke-width:2.5px,stroke-dasharray: 5 5;
```

---

## 🎨 1. Arsitektur Sisi Klien & Preview Kode (Flutter Client Structure)

Struktur kode aplikasi Flutter diatur menggunakan **Layered Architecture** dengan fokus penuh pada pemisahan state, UI, dan komunikasi server.

### A. Widget Tree & State Flow Visual Preview
Berikut adalah representasi visual bagaimana state dinamis disebarkan dari level teratas (`MaterialApp`) hingga ke Widget terkecil secara reaktif:

```mermaid
graph TD
    classDef mainClass fill:#EBF3FF,stroke:#1A3C8F,stroke-width:2px,color:#0A1E50;
    classDef notifierClass fill:#FFF9E6,stroke:#F4A900,stroke-width:2px,color:#6B4600;
    classDef screenClass fill:#EBFDF5,stroke:#0F6E56,stroke-width:2px,color:#053520;

    Root[main.dart - runApp]:::mainClass --> ThemeColor[ValueNotifier themeColorNotifier]:::notifierClass
    ThemeColor --> ThemeMode[ValueNotifier themeNotifier]:::notifierClass
    ThemeMode --> Material[MaterialApp]:::mainClass
    Material --> RouteWrapper[AuthWrapper]:::mainClass
    RouteWrapper -->|Authenticated| Home[MainNavScreen]:::screenClass
    RouteWrapper -->|Unauthenticated| Login[LoginScreen]:::screenClass
```

### B. Implementasi Nyata Pola Reactive State Management
Aplikasi menggunakan **`ValueNotifier`** untuk performa optimal tanpa pustaka eksternal yang berat. Contoh di bawah menunjukkan bagaimana warna tema primer (`RupiaColors.primary`) dimutasi dan didengarkan secara real-time di `lib/main.dart`:

```dart
// Mendefinisikan Notifier Global di main.dart
final ValueNotifier<Color> themeColorNotifier = ValueNotifier(RupiaColors.primary);

// Menghubungkan ke UI Root MaterialApp menggunakan ValueListenableBuilder
class RupiaChatApp extends StatelessWidget {
  const RupiaChatApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<Color>(
      valueListenable: themeColorNotifier,
      builder: (context, primaryColor, child) {
        return MaterialApp(
          title: 'RupiaChat',
          theme: ThemeData(
            colorScheme: ColorScheme.fromSeed(seedColor: primaryColor),
            useMaterial3: true,
          ),
          routes: {
            '/': (context) => const AuthWrapper(),
            '/login': (context) => const LoginScreen(),
            '/home': (context) => const MainNavScreen(),
          },
        );
      },
    );
  }
}
```

### C. Implementasi Nyata Pola Service Layer (Pusher Event Stream)
Seluruh API terenkapsulasi ke dalam kelas layanan terisolasi. Di bawah ini adalah cuplikan visual bagaimana `ChatService` memetakan **Pusher WebSocket Event** ke dalam **Dart Stream** reaktif yang dikonsumsi oleh UI untuk memperbarui daftar chat:

```dart
class ChatService {
  static final ChatService _instance = ChatService._internal();
  factory ChatService() => _instance;
  
  static final _pusher = PusherChannelsFlutter.getInstance();
  static final StreamController<PusherEvent> _globalEventController = StreamController<PusherEvent>.broadcast();

  // Mendengarkan pesan masuk secara real-time berdasarkan channel chat
  Stream<MessageModel> listenMessages(String roomId) {
    final channelName = 'chat.$roomId';
    
    // Auto-subscribe channel jika belum terdaftar
    _subscribeIfNew(channelName);

    return _globalEventController.stream
        .where((event) => event.channelName == channelName)
        .where((event) => event.eventName == 'MessageSent' || event.eventName == 'App\\Events\\MessageSent')
        .map((event) {
          final data = jsonDecode(event.data);
          return MessageModel.fromMap(data['message'], data['message']['id'].toString());
        });
  }
}
```

---

## 💻 2. Arsitektur Sisi Server (Laravel API Backend Architecture)

Backend dibangun di atas framework **Laravel** dengan fokus melayani API (*API-only application*) dengan struktur endpoint bersih dan keamanan tinggi.

### A. Proteksi Masukan & Alur Validasi
*   **Form Request Validation**: Proteksi ketat terhadap payload masukan, membatasi berkas seperti PDF dan Audio (AAC/MP3) hingga ukuran maksimal 10MB.
*   **Self-Healing Ngrok / Media URLs Helper**: Laravel mendeteksi *base URL* aktif (termasuk domain dinamis Ngrok) untuk menulis ulang berkas media lokal secara otomatis sebelum JSON dikirim ke Flutter, menjamin tidak ada tautan gambar/audio yang rusak (*broken link*).

---

## ☁️ 3. Infrastruktur & Integrasi Cloud (Cloud Integration)

RupiaChat memanfaatkan keunggulan multi-cloud untuk efisiensi performa dan keandalan data:

1.  **TiDB Cloud (Relational Database)**:
    *   Menggunakan basis data **TiDB Cloud (MySQL compatible)** yang didistribusikan secara global dengan performa transaksi ACID tinggi pada port `4000`, menjamin penyimpanan data obrolan, kontak, dan transaksi yang konsisten dan aman.
2.  **Supabase Storage (Media Bucket)**:
    *   Berkas lampiran premium (Gambar, PDF, Audio) diunggah langsung ke *bucket* Supabase melalui SDK, mengembalikan tautan publik permanen untuk mengurangi beban bandwidth pada server utama.
3.  **Real-Time WebSockets Gateway (Pusher)**:
    *   Mengalirkan status aktif pengguna, indikator sedang mengetik (*typing indicators*), centang dua tanda baca (*read receipts*), dan pesan baru langsung secara instan tanpa proses polling (*polling-free*).
4.  **Firebase Cloud Messaging (FCM Gateway)**:
    *   Memicu notifikasi *push* latar belakang berprioritas tinggi (*high-priority background notification*) untuk membangunkan aplikasi penerima saat ada panggilan telepon Agora masuk.

---

## 🛠️ Panduan Pengembangan Lokal (Local Development)

### Persyaratan Sistem (Prerequisites)
*   PHP `>= 8.2`
*   Composer `>= 2.0`
*   MySQL / TiDB Client

### Menjalankan Server API Lokal
1.  Salin konfigurasi lingkungan:
    ```bash
    cp .env.example .env
    ```
2.  Instal dependensi PHP:
    ```bash
    composer install
    ```
3.  Jalankan migrasi database:
    ```bash
    php artisan migrate
    ```
4.  Jalankan server pengembangan:
    ```bash
    php artisan serve
    ```
