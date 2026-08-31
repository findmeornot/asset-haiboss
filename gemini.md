# Panduan Pengembangan: Filament 5.7.3 (Custom Fork)

Project ini menggunakan **Filament versi 5.7.3** yang merupakan *custom enterprise fork*. Terdapat beberapa modifikasi struktur *namespace* dibandingkan dengan Filament v3 standar. 

Untuk menghindari *fatal error*, mohon perhatikan aturan *namespace* berikut saat memodifikasi atau membuat *Resource* / *Page* baru:

## 1. Arsitektur Form & Schema
- **`Schema` Wrapper:** 
  Gunakan `Filament\Schemas\Schema` (bukan `Filament\Forms\Schema`).
- **Komponen Layout/Struktural:**
  Komponen yang membungkus *field* (seperti `Section`, `Group`, `Grid`) berada di `Filament\Schemas\Components\...`.
- **Komponen Input:**
  Input reguler (seperti `TextInput`, `Select`, `Radio`, `ToggleButtons`, `FileUpload`) sebagian besar tetap berada di `Filament\Forms\Components\...`. 
  *(Tip: Gunakan `use Filament\Forms\Components;` lalu panggil `Components\TextInput::make(...)`).*

## 2. Arsitektur Action
- **Penyatuan Action:**
  TIDAK ADA spesifikasi `Filament\Tables\Actions\Action` atau `Filament\Forms\Actions\Action`.
  Seluruh aksi (*table*, *form*, maupun *page*) telah dilebur menjadi satu *namespace* global: **`Filament\Actions\Action`**.

## 3. Best Practice
Jika merasa ragu mengenai *namespace* sebuah fitur baru, disarankan untuk selalu merujuk (*read-only*) pada implementasi yang sudah ada di file `AssetResource.php` atau file sejenisnya sebelum menuliskan kode.
