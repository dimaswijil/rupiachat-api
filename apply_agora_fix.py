import os

def patch_file(filepath, replacements):
    print(f"Patching {filepath}...")
    if not os.path.exists(filepath):
        print(f"Error: {filepath} not found.")
        return False
        
    with open(filepath, "r", encoding="utf-8") as f:
        content = f.read()
        
    original_content = content
    
    for target, replacement in replacements:
        if target not in content:
            print(f"Warning: Target snippet not found in {filepath}:")
            print("-" * 40)
            print(target[:200] + "...")
            print("-" * 40)
            continue
        content = content.replace(target, replacement)
        
    if content == original_content:
        print(f"No changes made to {filepath}.")
        return False
        
    with open(filepath, "w", encoding="utf-8") as f:
        f.write(content)
    print(f"Successfully patched {filepath}.")
    return True

# Replacements for call_screen.dart
call_screen_replacements = [
    # 1. Add static lock
    (
        "class _CallScreenState extends State<CallScreen> with TickerProviderStateMixin {\n  RtcEngine? _engine;",
        "class _CallScreenState extends State<CallScreen> with TickerProviderStateMixin {\n  static Future<void>? _activeReleaseFuture;\n  RtcEngine? _engine;"
    ),
    # 2. Add permission check & await lock in _initAgora
    (
        "  Future<void> _initAgora() async {\n    // Request semua permission yang dibutuhkan (sesuai Agora official docs)\n    await [Permission.microphone, Permission.camera, Permission.bluetooth, Permission.bluetoothConnect].request();\n\n    if (!mounted) return;",
        """  Future<void> _initAgora() async {
    // Request semua permission yang dibutuhkan (sesuai Agora official docs)
    final statuses = await [
      Permission.microphone,
      Permission.camera,
      Permission.bluetooth,
      Permission.bluetoothConnect,
    ].request();

    if (!mounted) return;

    // Cek secara spesifik apakah permission diberikan
    final micGranted = statuses[Permission.microphone]?.isGranted ?? false;
    final cameraGranted = statuses[Permission.camera]?.isGranted ?? false;

    if (!micGranted || (widget.isVideoCall && !cameraGranted)) {
      debugPrint('⚠️ Peringatan: Perekam suara atau Kamera tidak diizinkan.');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('⚠️ Panggilan membutuhkan izin Kamera & Mikrofon'),
            backgroundColor: Colors.orange,
          ),
        );
      }
    }

    // Await static release future dari screen sebelumnya untuk mencegah tabrakan native singleton
    if (_activeReleaseFuture != null) {
      debugPrint('⏳ Menunggu engine sebelumnya selesai release...');
      try {
        await _activeReleaseFuture;
      } catch (e) {
        debugPrint('⚠️ Error saat menunggu release engine lama: $e');
      }
      _activeReleaseFuture = null;
      debugPrint('✅ Engine sebelumnya selesai release');
    }"""
    ),
    # 3. Add enableLocalVideo in _initAgora video config section
    (
        """    // 5c. Video config
    try {
      if (widget.isVideoCall) {
        await _engine!.enableVideo();
        await _engine!.setVideoEncoderConfiguration(
          const VideoEncoderConfiguration(
            dimensions: VideoDimensions(width: 640, height: 480),
            frameRate: 15,
            bitrate: 0,
            orientationMode: OrientationMode.orientationModeAdaptive,
          ),
        );
        await _engine!.startPreview();
        debugPrint('✅ Video enabled + preview started');
      } else {
        await _engine!.disableVideo();
        debugPrint('✅ Video disabled (voice call)');
      }""",
        """    // 5c. Video config
    try {
      if (widget.isVideoCall) {
        await _engine!.enableVideo();
        await _engine!.enableLocalVideo(true);
        await _engine!.setVideoEncoderConfiguration(
          const VideoEncoderConfiguration(
            dimensions: VideoDimensions(width: 640, height: 480),
            frameRate: 15,
            bitrate: 0,
            orientationMode: OrientationMode.orientationModeAdaptive,
          ),
        );
        await _engine!.startPreview();
        debugPrint('✅ Video enabled + preview started');
      } else {
        await _engine!.disableVideo();
        await _engine!.enableLocalVideo(false);
        debugPrint('✅ Video disabled (voice call)');
      }"""
    ),
    # 4. Add enableLocalVideo in toggle camera & upgrade video
    (
        """  void _toggleCamera() {
    if (_engine == null || !_isEngineInitialized) return;
    setState(() => _cameraOff = !_cameraOff);
    _engine!.muteLocalVideoStream(_cameraOff);
  }

  void _switchCamera() {
    if (_engine == null || !_isEngineInitialized) return;
    _engine!.switchCamera();
  }

  void _upgradeToVideo() async {
    if (_engine == null || !_isEngineInitialized) return;
    await _engine!.enableVideo();
    await _engine!.startPreview();""",
        """  void _toggleCamera() {
    if (_engine == null || !_isEngineInitialized) return;
    setState(() => _cameraOff = !_cameraOff);
    _engine!.muteLocalVideoStream(_cameraOff);
    _engine!.enableLocalVideo(!_cameraOff);
  }

  void _switchCamera() {
    if (_engine == null || !_isEngineInitialized) return;
    _engine!.switchCamera();
  }

  void _upgradeToVideo() async {
    if (_engine == null || !_isEngineInitialized) return;
    await _engine!.enableVideo();
    await _engine!.enableLocalVideo(true);
    await _engine!.startPreview();"""
    ),
    # 5. Modify dispose and _disposeAgora
    (
        """  Future<void> _disposeAgora() async {
    if (_engine == null) return;
    
    if (_joined) {
      try { await _engine!.leaveChannel(); } catch (_) {}
      _joined = false;
    }
    
    try { await _engine!.stopPreview(); } catch (_) {}
    try { await _engine!.release(); } catch (_) {}
    _engine = null;
    _isEngineInitialized = false;
    _engineReady = false;
  }

  @override
  void dispose() {
    _stopTimer();
    _noAnswerTimer?.cancel();
    _hideControlsTimer?.cancel();
    _pulseController.dispose();
    _fadeController.dispose();
    // FIXED Bug #5: dispose() tidak bisa await, tapi _endCall() (yang SUDAH await
    // _disposeAgora()) selalu dipanggil sebelum navigator pop.
    // Di sini kita tangkap referensi engine lalu null-kan segera agar UI tidak akses,
    // kemudian fire-and-forget cleanup yang sebenarnya.
    final engineRef = _engine;
    _engine = null;
    _isEngineInitialized = false;
    _engineReady = false;
    if (engineRef != null) {
      // Fire-and-forget — aman karena engine sudah di-null dari state
      Future(() async {
        try { await engineRef.leaveChannel(); } catch (_) {}
        try { await engineRef.stopPreview(); } catch (_) {}
        try { await engineRef.release(); } catch (_) {}
      });
    }
    _ringbackPlayer.dispose();
    super.dispose();
  }""",
        """  Future<void> _disposeAgora() async {
    if (_engine == null) return;
    
    final engineRef = _engine;
    _engine = null;
    _isEngineInitialized = false;
    _engineReady = false;

    final releaseFuture = Future(() async {
      if (_joined) {
        try { await engineRef.leaveChannel(); } catch (_) {}
      }
      try { await engineRef.stopPreview(); } catch (_) {}
      try { await engineRef.release(); } catch (_) {}
    });

    _activeReleaseFuture = releaseFuture;
    await releaseFuture;
    _joined = false;
  }

  @override
  void dispose() {
    _stopTimer();
    _noAnswerTimer?.cancel();
    _hideControlsTimer?.cancel();
    _pulseController.dispose();
    _fadeController.dispose();
    // FIXED Bug #5: dispose() tidak bisa await, tapi _endCall() (yang SUDAH await
    // _disposeAgora()) selalu dipanggil sebelum navigator pop.
    // Di sini kita tangkap referensi engine lalu null-kan segera agar UI tidak akses,
    // kemudian fire-and-forget cleanup yang sebenarnya.
    final engineRef = _engine;
    _engine = null;
    _isEngineInitialized = false;
    _engineReady = false;
    if (engineRef != null) {
      final releaseFuture = Future(() async {
        try { await engineRef.leaveChannel(); } catch (_) {}
        try { await engineRef.stopPreview(); } catch (_) {}
        try { await engineRef.release(); } catch (_) {}
      });
      _activeReleaseFuture = releaseFuture;
    }
    _ringbackPlayer.dispose();
    super.dispose();
  }"""
    ),
    # 6. Switch from native SurfaceView to Flutter Texture rendering on Android
    (
        "useFlutterTexture: !Platform.isAndroid,",
        "useFlutterTexture: true,"
    ),
    (
        "useAndroidSurfaceView: Platform.isAndroid,",
        "useAndroidSurfaceView: false,"
    )
]

# Replacements for group_call_screen.dart
group_call_screen_replacements = [
    # 1. Add static lock
    (
        "class _GroupCallScreenState extends State<GroupCallScreen>\n    with TickerProviderStateMixin {\n  // FIXED: Nullable engine — mencegah LateInitializationError jika init gagal\n  RtcEngine? _engine;",
        "class _GroupCallScreenState extends State<GroupCallScreen>\n    with TickerProviderStateMixin {\n  static Future<void>? _activeReleaseFuture;\n  // FIXED: Nullable engine — mencegah LateInitializationError jika init gagal\n  RtcEngine? _engine;"
    ),
    # 2. Add permission check & await lock in _initAgora
    (
        """  Future<void> _initAgora() async {
    await [Permission.microphone, Permission.camera].request();

    // FIXED Bug #15: mounted check setelah async permission request
    if (!mounted) return;""",
        """  Future<void> _initAgora() async {
    final statuses = await [
      Permission.microphone,
      Permission.camera,
      if (Platform.isAndroid || Platform.isIOS) Permission.bluetooth,
      if (Platform.isAndroid || Platform.isIOS) Permission.bluetoothConnect,
    ].request();

    // FIXED Bug #15: mounted check setelah async permission request
    if (!mounted) return;

    final micGranted = statuses[Permission.microphone]?.isGranted ?? false;
    final cameraGranted = statuses[Permission.camera]?.isGranted ?? false;

    if (!micGranted || (widget.isVideoCall && !cameraGranted)) {
      debugPrint('⚠️ Peringatan: Perekam suara atau Kamera tidak diizinkan.');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('⚠️ Panggilan membutuhkan izin Kamera & Mikrofon'),
            backgroundColor: Colors.orange,
          ),
        );
      }
    }

    // Await static release future dari screen sebelumnya untuk mencegah tabrakan native singleton
    if (_activeReleaseFuture != null) {
      debugPrint('⏳ Menunggu engine group sebelumnya selesai release...');
      try {
        await _activeReleaseFuture;
      } catch (e) {
        debugPrint('⚠️ Error saat menunggu release engine group lama: $e');
      }
      _activeReleaseFuture = null;
      debugPrint('✅ Engine group sebelumnya selesai release');
    }"""
    ),
    # 3. Add enableLocalVideo in _initAgora video config section
    (
        """    // Video config
    try {
      if (_isVideoMode) {
        await _engine!.enableVideo();
        await _engine!.startPreview();
        debugPrint('✅ Group video enabled + preview started');
      } else {
        await _engine!.disableVideo();
        debugPrint('✅ Group video disabled (voice call)');
      }""",
        """    // Video config
    try {
      if (_isVideoMode) {
        await _engine!.enableVideo();
        await _engine!.enableLocalVideo(true);
        await _engine!.startPreview();
        debugPrint('✅ Group video enabled + preview started');
      } else {
        await _engine!.disableVideo();
        await _engine!.enableLocalVideo(false);
        debugPrint('✅ Group video disabled (voice call)');
      }"""
    ),
    # 4. Add enableLocalVideo in toggle camera & upgrade video
    (
        """  void _toggleCamera() {
    if (_engine == null || !_engineReady) return;
    setState(() => _cameraOff = !_cameraOff);
    _engine!.muteLocalVideoStream(_cameraOff);
  }

  void _switchCamera() { if (_engine != null && _engineReady) _engine!.switchCamera(); }

  void _upgradeToVideo() async {
    if (_engine == null || !_engineReady) return;
    await _engine!.enableVideo();
    await _engine!.startPreview();""",
        """  void _toggleCamera() {
    if (_engine == null || !_engineReady) return;
    setState(() => _cameraOff = !_cameraOff);
    _engine!.muteLocalVideoStream(_cameraOff);
    _engine!.enableLocalVideo(!_cameraOff);
  }

  void _switchCamera() { if (_engine != null && _engineReady) _engine!.switchCamera(); }

  void _upgradeToVideo() async {
    if (_engine == null || !_engineReady) return;
    await _engine!.enableVideo();
    await _engine!.enableLocalVideo(true);
    await _engine!.startPreview();"""
    ),
    # 5. Modify dispose and _disposeEngine
    (
        """  @override
  void dispose() {
    _timer?.cancel();
    _pulseController.dispose();
    // FIXED Bug #8: dispose() tidak bisa await. _endCall() (yang SUDAH await
    // _disposeEngine()) selalu dipanggil sebelum navigator pop.
    // Safety net: null-kan referensi segera, fire-and-forget cleanup.
    final engineRef = _engine;
    _engine = null;
    _engineReady = false;
    if (engineRef != null) {
      Future(() async {
        try { await engineRef.leaveChannel(); } catch (_) {}
        try { await engineRef.stopPreview(); } catch (_) {}
        try { await engineRef.release(); } catch (_) {}
      });
    }
    super.dispose();
  }

  Future<void> _disposeEngine() async {
    if (_engine == null) return;
    try { await _engine!.leaveChannel(); } catch (_) {}
    try { await _engine!.stopPreview(); } catch (_) {}
    try { await _engine!.release(); } catch (_) {}
    _engine = null;
    _engineReady = false;
    _isEngineInitialized = false;
  }""",
        """  @override
  void dispose() {
    _timer?.cancel();
    _pulseController.dispose();
    // FIXED Bug #8: dispose() tidak bisa await. _endCall() (yang SUDAH await
    // _disposeEngine()) selalu dipanggil sebelum navigator pop.
    // Safety net: null-kan referensi segera, fire-and-forget cleanup.
    final engineRef = _engine;
    _engine = null;
    _engineReady = false;
    if (engineRef != null) {
      final releaseFuture = Future(() async {
        try { await engineRef.leaveChannel(); } catch (_) {}
        try { await engineRef.stopPreview(); } catch (_) {}
        try { await engineRef.release(); } catch (_) {}
      });
      _activeReleaseFuture = releaseFuture;
    }
    super.dispose();
  }

  Future<void> _disposeEngine() async {
    if (_engine == null) return;
    
    final engineRef = _engine;
    _engine = null;
    _engineReady = false;
    _isEngineInitialized = false;

    final releaseFuture = Future(() async {
      try { await engineRef.leaveChannel(); } catch (_) {}
      try { await engineRef.stopPreview(); } catch (_) {}
      try { await engineRef.release(); } catch (_) {}
    });

    _activeReleaseFuture = releaseFuture;
    await releaseFuture;
  }"""
    ),
    # 6. Switch from native SurfaceView to Flutter Texture rendering on Android
    (
        "useFlutterTexture: !Platform.isAndroid,",
        "useFlutterTexture: true,"
    ),
    (
        "useAndroidSurfaceView: Platform.isAndroid,",
        "useAndroidSurfaceView: false,"
    )
]

patch_file("/Users/mac/StudioProjects/rupiachat/lib/screens/call/call_screen.dart", call_screen_replacements)
patch_file("/Users/mac/StudioProjects/rupiachat/lib/screens/call/group_call_screen.dart", group_call_screen_replacements)
print("All tasks completed.")
