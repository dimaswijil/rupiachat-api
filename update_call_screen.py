import sys

filepath = '/Users/mac/StudioProjects/rupiachat/lib/screens/call/call_screen.dart'
with open(filepath, 'r') as f:
    content = f.read()

# Add import
if "package:audioplayers/audioplayers.dart" not in content:
    content = content.replace("import 'package:flutter/material.dart';", "import 'package:flutter/material.dart';\nimport 'package:audioplayers/audioplayers.dart';")

# Add AudioPlayer declaration
if "AudioPlayer _ringbackPlayer = AudioPlayer();" not in content:
    content = content.replace("Timer? _noAnswerTimer; // Timeout jika tidak diangkat", "Timer? _noAnswerTimer; // Timeout jika tidak diangkat\n  final AudioPlayer _ringbackPlayer = AudioPlayer();")

# Play ringback tone if not incoming
play_snippet = """
    if (!widget.isIncoming) {
      _playRingbackTone();
    }
"""
if "void _playRingbackTone()" not in content:
    content = content.replace("void _startTimer() {", """
  void _playRingbackTone() async {
    await _ringbackPlayer.setReleaseMode(ReleaseMode.loop);
    await _ringbackPlayer.play(AssetSource('audio/ringback.wav'));
  }

  void _startTimer() {""")

    content = content.replace("_initAgora();\n  }", "_initAgora();\n" + play_snippet + "\n  }")

# Stop playing when remote user joins
if "_ringbackPlayer.stop();" not in content:
    content = content.replace("_remoteUid = remoteUid;\n            });", "_remoteUid = remoteUid;\n            });\n            _ringbackPlayer.stop();")

# Stop in _endCall
if "_ringbackPlayer.stop();" in content and "void _endCall() {" in content and "_ringbackPlayer.stop();" not in content[content.find("void _endCall() {"):]:
    content = content.replace("void _endCall() {\n    if (_isEnding) return;\n    _isEnding = true;", "void _endCall() {\n    if (_isEnding) return;\n    _isEnding = true;\n    _ringbackPlayer.stop();")

# Dispose player
if "_ringbackPlayer.dispose();" not in content:
    content = content.replace("try { _engine.leaveChannel(); _engine.release(); } catch (_) {}", "try { _engine.leaveChannel(); _engine.release(); } catch (_) {}\n    _ringbackPlayer.dispose();")

with open(filepath, 'w') as f:
    f.write(content)

print("Done")
