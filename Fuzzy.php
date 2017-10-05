<?php

// conn.php
mysql_connect("localhost", "root", "password");
mysql_select_db('fuzzy');

/**
 * Description of Fuzzy
 *
 * @author 
 */
class Fuzzy {

  // nama saja
  var $baba = 'batas_bawah', $baat = 'batas_atas';
  var $debug = true;
  // all db load
  var $all_kriteria
          , $all_aturan
          , $all_parameter;
  // post_handle
  var $suhu, $ph, $sal, $tds;
  // tabel yg diload
  var $tabels = ['aturan', 'kriteria', 'parameter'];
  // do id_parameter
  var $do_id_parameter = false;
  // himpunan
  var $himpunan_kriteria = [];
  var $himpunan_possibility = [];
  var $himpunan_alpha_z = [];

  function __construct($suhu, $ph, $tds, $sal) {
    // create var class
    $this->suhu = $suhu;
    $this->ph = $ph;
    $this->sal = $sal;
    $this->tds = $tds;
    $this->all_aturan = $this->load_database($this->tabels[0], 'id_aturan');
    $this->all_kriteria = $this->load_database($this->tabels[1], 'id_kriteria');
    $this->all_parameter = $this->load_database($this->tabels[2], 'nama_parameter');
    foreach ($this->all_parameter as $apk => $apv) {
      if ($apv["nama_parameter"] == "do") {
        $this->do_id_parameter = $apv["id_parameter"];
      }
    }
    $this->step_one_kriteria();
  }

  function debug($msg) {
    if ($this->debug)
      print $msg . '<br />';
  }

  function load_database($name, $id) {
    $this->debug("<b>Load Database $name</b>");
    $q = mysql_query("SELECT * FROM $name") or die(mysql_error());
    $r = [];
    while ($w = mysql_fetch_array($q)) {
      $r[$w[$id]] = $w;
    }
    return $r;
  }

  function load_query($query) {
    $q = mysql_query($query) or die(mysql_error());
    $r = [];
    while ($w = mysql_fetch_array($q)) {
      $r[] = $w;
    }
    return $r;
  }

  function step_one_create_table() {
    $w = '<table border=1>';
    foreach ($this->himpunan_kriteria as $k => $v) {
      $w .= '<tr>'
              . '<td>' . $k . '</td>'
              . '<td>nilai: ' . $v["nilai"] . '</td>'
              . '<td>Kriteria</td>';
      $w .= '<td>';
      foreach ($v["kriteria"] as $kk => $kv) {
        $ww = '<table border=1>';
        if ($kv) {
          $ww .= '<tr><td>' . $kk . '</td><td>' . $kv . '</td></tr>';
        }
        $ww .= '</table>';
        $w .= $ww;
      }
      $w .= '</td>';
      $w .= '</tr>';
    }
    $w .= '</table>';
    $this->debug($w);
  }

  function step_one_kriteria() {
    $this->debug("<h2>Mencari Kriteria</h2>");
    // iterate all kriteria
    $this->himpunan_kriteria['suhu'] = $this->iterate_aturan(
            $this->all_parameter['suhu']['id_parameter'], $this->suhu);
    $this->himpunan_kriteria['ph'] = $this->iterate_aturan(
            $this->all_parameter['ph']['id_parameter'], $this->ph);
    $this->himpunan_kriteria['tds'] = $this->iterate_aturan(
            $this->all_parameter['tds']['id_parameter'], $this->tds);
    $this->himpunan_kriteria['salinitas'] = $this->iterate_aturan(
            $this->all_parameter['salinitas']['id_parameter'], $this->sal);
    $this->step_one_create_table();
    // $this->debug('<pre>' . json_encode($this->himpunan_kriteria, JSON_PRETTY_PRINT) . '</pre>');
    $this->step_two_possibility();
  }

  function eval_hasil($nilai, $rumus) {
    return eval('return ((' . str_replace(array('x', '/'), array($nilai, ')/('), $rumus) . '));');
  }

  function iterate_aturan($id_parameter, $nilai) {
    $r = [];
    foreach ($this->all_kriteria as $krit) {
      if ($krit['id_parameter'] == $id_parameter) {
        // baba = batas atas
        if (!isset($r[$krit["nama_kriteria"]])) {
          if ($krit[$this->baba] && $krit[$this->baat]) {
            // if ada dua duanya baba dan baat
            if (eval('return '
                            . '(' . str_replace('x', $nilai, $krit[$this->baba]) . ' AND '
                            . str_replace('x', $nilai, $krit[$this->baat]) . ');')) {
              // return
              $r[$krit["nama_kriteria"]] = $this->eval_hasil($nilai, $krit["hasil"]);
            }
          } else {
            // if hanya ada satu saja
            if ($krit[$this->baba]) {
              if (eval('return (' . str_replace('x', $nilai, $krit[$this->baba]) . ');')) {
                $r[$krit["nama_kriteria"]] = $this->eval_hasil($nilai, $krit["hasil"]);
              }
            }
            if ($krit[$this->baat]) {
              if (eval('return (' . str_replace('x', $nilai, $krit[$this->baat]) . ');')) {
                $r[$krit["nama_kriteria"]] = $this->eval_hasil($nilai, $krit["hasil"]);
              }
            }
          }
        }
      }
    }
    return array(
        "nilai" => $nilai,
        "kriteria" => $r
    );
  }

  function step_two_create_table() {
    $w = '<table border=1>';
    $w .= '<tr>'
            . '<td>nomor</td>'
            . '<td colspan=2>suhu</td>'
            . '<td colspan=2>ph</td>'
            . '<td colspan=2>salinitas</td>'
            . '<td colspan=2>tds</td>'
            . '</tr>'
    ;
    $no = 1;
    foreach ($this->himpunan_possibility as $k => $v) {
      $w .= '<tr>'
              . '<td>' . $no . '</td>';
      foreach (array("suhu", "ph", "tds", "salinitas") as $kk) {
        $w .= '<td>' . $v[$kk]["nama"] . '</td>';
        $w .= '<td>' . $v[$kk]["nilai"] . '</td>';
      }
      $w .= '</tr>';
      $no++;
    }
    $w .= '</table>';
    $this->debug($w);
  }

  function step_two_possibility() {
    $this->debug("<h2>Mencari Possibility</h2>");
    foreach ($this->himpunan_kriteria["suhu"]["kriteria"] as $ksuhu => $vsuhu) {
      if ($vsuhu) {
        foreach ($this->himpunan_kriteria["ph"]["kriteria"] as $kph => $vph) {
          if ($vph) {
            foreach ($this->himpunan_kriteria["salinitas"]["kriteria"] as $ksal => $vsal) {
              if ($vsal) {
                foreach ($this->himpunan_kriteria["tds"]["kriteria"] as $ktds => $vtds) {
                  if ($vtds) {
                    $this->himpunan_possibility[] = array(
                        "suhu" => array("nama" => $ksuhu, "nilai" => $vsuhu),
                        "ph" => array("nama" => $kph, "nilai" => $vph),
                        "tds" => array("nama" => $ktds, "nilai" => $vtds),
                        "salinitas" => array("nama" => $ksal, "nilai" => $vsal),
                    );
                  }
                }
              }
            }
          }
        }
      }
    }
    $this->step_two_create_table();
    // $this->debug('<pre>' . json_encode($this->himpunan_possibility, JSON_PRETTY_PRINT) . '</pre>');
    $this->step_three_alpha_and_z();
  }

  /*
   * Khusus untuk menghitung algo terbalik
   */

  function algo_balik($alpha, $hasil) { // menghitung x jika = 0.5
    $pers = explode("/", $hasil); // membagi ke array
    $pembagian_bawah = eval('return ' . $pers[1] . ';');
    $alpha_dikali_pembagian_bawah = $alpha * $pembagian_bawah;
    // jika x didepan, maka hasil belakang + a x pb 
    // ex: 0.5 = 2 - x. jadi 2 - 0.5
    // ex: 0.5 = x - 2. jadi 2 + 0.5
    if (substr($pers[0], 0, 1) == "x") {
      $x_and_angka = explode("-", $pers[0]);
      // angka di second
      return ($x_and_angka[1] + $alpha_dikali_pembagian_bawah);
    } else {
      $x_and_angka = explode("-", $pers[0]);
      // angka di kedua
      return ($x_and_angka[0] - $alpha_dikali_pembagian_bawah);
    }
  }

  function step_three_create_table() {
    $w = '<table border=1>';
    $w .= '<tr>'
            . '<td>nomor</td>';
    $keys = [];
    foreach ($this->himpunan_alpha_z as $k => $v) {
      foreach ($v as $kk => $vv) {
        $keys[] = $kk;
        $w .= '<td' .
                (is_array($vv) ? ' colspan=2' : '')
                . '>' . $kk . '</td>';
      }
      break;
    }
    $w .= '</tr>';
    ;
    $no = 1;
    foreach ($this->himpunan_alpha_z as $k => $v) {
      $w .= '<tr>'
              . '<td>' . $no . '</td>';
      foreach ($keys as $kk) {
        if (is_array($v[$kk])) {
          foreach ($v[$kk] as $kkk => $vvv) {
            $w .= '<td>' . $vvv . '</td>';
          }
        } else {
          $w .= '<td>' . $v[$kk] . '</td>';
        }
      }
      $w .= '</tr>';
      $no++;
    }
    $w .= '</table>';
    $this->debug($w);
  }

  function step_three_alpha_and_z() {
    $this->debug("<h2>Mencari Alpha, Z dan Defuzz</h2>");
    foreach ($this->himpunan_possibility as $hk => $hv) {
      // searching RXXX in rules
      foreach ($this->all_aturan as $at) {
        if (
                $at["suhu"] == $hv["suhu"]["nama"]
                AND $at["ph"] == $hv["ph"]["nama"]
                AND $at["tds"] == $hv["tds"]["nama"]
                AND $at["salinitas"] == $hv["salinitas"]["nama"]
        ) {
          // action with do here
          $this_do = $at["do"];
          // create alpha
          $this_alpha = min(array(
              $hv["suhu"]["nilai"], $hv["ph"]["nilai"]
              , $hv["tds"]["nilai"], $hv["salinitas"]["nilai"]
          ));
          $do_naik = 0;
          $do_turun = 0;
          $z_predikat_1 = 0;
          $z_predikat_2 = 0;

          $rumus_batas = array(
              "atas" => false,
              "bawah" => false
          );
          // loop kriteria lagi dari id doF
          $database_do_ini = $this->load_query("SELECT * FROM kriteria "
                  . "WHERE id_parameter='" . $this->do_id_parameter . "' AND "
                  . "nama_kriteria='$this_do' ORDER BY tahap ASC");
          foreach ($database_do_ini as $done) {
            if ($done["hasil"] != "1") {
              if ($done["hasil"] != "0") {
                if (!$rumus_batas["atas"]) {
                  $rumus_batas["atas"] = $done["hasil"];
                } else {
                  $rumus_batas["bawah"] = $done["hasil"];
                }
              }
            }
          }
          if ($rumus_batas["atas"]) {
            $do_naik = $this->algo_balik($this_alpha, $rumus_batas["atas"]);
            $z_predikat_1 = $do_naik * $this_alpha;
          }
          if ($rumus_batas["bawah"]) {
            $do_turun = $this->algo_balik($this_alpha, $rumus_batas["bawah"]);
            $z_predikat_2 = $do_turun * $this_alpha;
          }
          $this->himpunan_alpha_z[] = array_merge($hv, array(
              "rumus_do" => $this_do,
              "alpha" => $this_alpha,
              "naik" => $do_naik,
              "turun" => $do_turun,
              "rumus" => $rumus_batas,
              "z_predikat1" => $z_predikat_1,
              "z_predikat2" => $z_predikat_2,
          ));
        }
      }
    }
    $this->step_three_create_table();
    // $this->debug("<pre>" . json_encode($this->himpunan_alpha_z, JSON_PRETTY_PRINT) . "</pre>");
    $this->step_four_nilaiz();
  }

  function step_four_nilaiz() {
    $this->debug("<h2>Mencari Nilai Z</h2>");
    $loop_total_alpha = 0;
    $loop_total_z_predikat = 0;
    foreach ($this->himpunan_alpha_z as $alz) {
      $loop_total_alpha = $loop_total_alpha + $alz["alpha"];
      $loop_total_z_predikat = $loop_total_z_predikat + $alz["z_predikat1"];
      $loop_total_z_predikat = $loop_total_z_predikat + $alz["z_predikat2"];
    }
    // bagika
    $hasil_bagi_totalz_dan_alpha = $loop_total_z_predikat / $loop_total_alpha;
    $this->debug("total (Z*predikat) n / total alpha");
    $this->debug("$loop_total_z_predikat / $loop_total_alpha = " . ($hasil_bagi_totalz_dan_alpha));
    $this->step_five_do($hasil_bagi_totalz_dan_alpha);
  }

  function step_five_do($hasil_bagi) {
    $this->debug("<h2>Mencari D.O</h2>");
    $c = $this->iterate_aturan($this->do_id_parameter, $hasil_bagi);
    var_dump($c);
  }

}

new Fuzzy(24, 7.5, 5500, 4);
