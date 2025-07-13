<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Carbon\Carbon;


class JadwalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Jadwal::where('user_id', $user->id);

        // Filter hari
        if ($request->has('hari')) {
            $hari = $request->query('hari');
            $query->where('hari', $hari);
        }

        // Filter kategori
        if ($request->has('kategori')) {
            $kategori = $request->query('kategori');
            $query->where('kategori', $kategori);
        }

        // Filter waktu berdasarkan jam untuk pagi/siang/sore/malam
        if ($request->has('waktu')) {
            $waktu = $request->query('waktu');
            switch (strtolower($waktu)) {
                case 'pagi':
                    $query->whereBetween('jam', ['05:00:00', '11:59:59']);
                    break;
                case 'siang':
                    $query->whereBetween('jam', ['12:00:00', '15:59:59']);
                    break;
                case 'sore':
                    $query->whereBetween('jam', ['16:00:00', '18:59:59']);
                    break;
                case 'malam':
                    $query->where(function ($q) {
                        $q->whereBetween('jam', ['19:00:00', '23:59:59'])
                            ->orWhereBetween('jam', ['00:00:00', '04:59:59']);
                    });
                    break;
            }
        }

        // Pencarian kata kunci di aktivitas
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where('aktivitas', 'like', "%$search%");
        }

        $jadwals = $query->orderBy('hari')->orderBy('jam')->get();

        return response()->json($jadwals);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam' => 'required|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam',
            'aktivitas' => 'required|string',
            'kategori' => 'required|string',
            'timer_durasi' => 'nullable|integer|min:1',
        ]);

        $jadwal = Jadwal::create([
            'user_id' => Auth::id(),
            'hari' => $validated['hari'],
            'jam' => $validated['jam'],
            'jam_selesai' => $validated['jam_selesai'] ?? null,
            'aktivitas' => $validated['aktivitas'],
            'kategori' => $validated['kategori'],
            'timer_durasi' => $validated['timer_durasi'] ?? null,
            'status' => 'Belum Mulai',
        ]);

        return response()->json($jadwal, 201);
    }

    public function show($id)
    {
        $jadwal = Jadwal::where('user_id', Auth::id())->findOrFail($id);
        return response()->json($jadwal);
    }

    public function update(Request $request, $id)
    {
        $jadwal = Jadwal::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'hari' => 'sometimes|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
            'jam' => 'sometimes|date_format:H:i',
            'jam_selesai' => 'nullable|date_format:H:i|after:jam',
            'aktivitas' => 'sometimes|string',
            'kategori' => 'sometimes|string',
            'evaluasi' => 'nullable|string',
            'status' => 'nullable|in:Selesai,Belum Mulai',
            'timer_durasi' => 'nullable|integer|min:1',
            'timer_start' => 'nullable|date',
        ]);

        $jadwal->update($validated);

        return response()->json($jadwal);
    }

    public function destroy($id)
    {
        $jadwal = Jadwal::where('user_id', Auth::id())->findOrFail($id);
        $jadwal->delete();

        return response()->json(['message' => 'Jadwal deleted']);
    }

    // Highlight aktivitas terdekat dalam 1 jam
    public function highlight(Request $request)
    {
        \Carbon\Carbon::setLocale('id');

        $user = $request->user();

        $now = \Carbon\Carbon::now('Asia/Jakarta');
        $oneHourLater = $now->copy()->addHour();
        $hariSekarang = $now->translatedFormat('dddd');

        $jadwal = Jadwal::where('user_id', $user->id)
            ->where('hari', $hariSekarang)
            ->whereTime('jam', '>=', $now->format('H:i'))
            ->whereTime('jam', '<=', $oneHourLater->format('H:i'))
            ->orderBy('jam')
            ->first();

        if (!$jadwal) {
            return response()->json([
                'message' => 'Tidak ada jadwal ditemukan',
                'now' => $now->format('H:i'),
                'one_hour_later' => $oneHourLater->format('H:i'),
                'hari' => $hariSekarang
            ]);
        }

        return response()->json([
            'hari' => $jadwal->hari,
            'jam' => $jadwal->jam,
            'jam_selesai' => $jadwal->jam_selesai,
            'aktivitas' => $jadwal->aktivitas,
            'kategori' => $jadwal->kategori,
        ]);
    }

    public function startTimer(Request $request, $id)
    {
        $jadwal = Jadwal::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $jadwal->timer_type = $request->input('timer_type', 'pomodoro');
        $jadwal->timer_durasi = $request->input('timer_durasi', 25);
        $jadwal->timer_start = Carbon::now();
        $jadwal->status = 'Sedang Berjalan';

        if ($jadwal->timer_type === 'pomodoro') {
            $jadwal->timer_end = Carbon::now()->addMinutes($jadwal->timer_durasi);
        }

        $jadwal->save();

        return response()->json([
            'message' => 'Timer dimulai.',
            'jadwal' => $jadwal
        ]);
    }

    public function stopTimer(Request $request, $id)
    {
        $jadwal = Jadwal::where('id', $id)->where('user_id', $request->user()->id)->firstOrFail();

        $jadwal->timer_end = Carbon::now();
        $jadwal->status = 'Selesai';
        $jadwal->save();

        return response()->json([
            'message' => 'Timer dihentikan & aktivitas ditandai selesai.',
            'jadwal' => $jadwal
        ]);
    }
}
