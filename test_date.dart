void main() {
  String raw = "2026-04-09 03:28:00";
  raw = "${raw}Z";
  DateTime? dt = DateTime.tryParse(raw);
  print(dt);
  print(dt?.toLocal());
}
