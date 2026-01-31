'use strict';
const MANIFEST = 'flutter-app-manifest';
const TEMP = 'flutter-temp-cache';
const CACHE_NAME = 'flutter-app-cache';

const RESOURCES = {"assets/AssetManifest.bin": "77a42ada43116e9abd57b0c50f4d176b",
"assets/AssetManifest.bin.json": "17a33200e0c7d8d397d50cadd7c07bd0",
"assets/assets/fonts/NotoNaskh/NotoNaskhArabic-bold.ttf": "3e8ac1d70691caf5ddfd36fee8acce3d",
"assets/assets/fonts/NotoNaskh/NotoNaskhArabic-medium.ttf": "cf9d0f200cd51add0cb088b547c3cf19",
"assets/assets/fonts/NotoNaskh/NotoNaskhArabic-regular.ttf": "08f2d6bfe92d3e78721a0e1746397344",
"assets/assets/fonts/NotoNaskh/NotoNaskhArabic-semiBold.ttf": "f3ce5cace408a28db3f7ad68a53e7ae2",
"assets/assets/fonts/NotoSans/NotoSans-bold.ttf": "2ea5e0855d5a3ec3f561b5bc62b39805",
"assets/assets/fonts/NotoSans/NotoSans-italic.ttf": "a6d070775dd5e6bfff61870528c6248a",
"assets/assets/fonts/NotoSans/NotoSans-light.ttf": "1e81ec98e0668cbee241a1f0a0ab90ad",
"assets/assets/fonts/NotoSans/NotoSans-medium.ttf": "a1311858ffd88b69aa5eadafd8f5c164",
"assets/assets/fonts/NotoSans/NotoSans-regular.ttf": "f46b08cc90d994b34b647ae24c46d504",
"assets/assets/fonts/OpenSans/OpenSans-bold.ttf": "0a191f83602623628320f3d3c667a276",
"assets/assets/fonts/OpenSans/OpenSans-light.ttf": "c87e3b21e46c872774d041a71e181e61",
"assets/assets/fonts/OpenSans/OpenSans-medium.ttf": "dac0e601db6e3601159b4aae5c1fda39",
"assets/assets/fonts/OpenSans/OpenSans-regular.ttf": "931aebd37b54b3e5df2fedfce1432d52",
"assets/assets/images/forgot_password.png": "62ada4bd5d4440e77ade8390ce3ff9f9",
"assets/assets/images/gas.png": "87ca71acdadc6969bc5a202b99c4c794",
"assets/assets/images/login.png": "4594408bd019bb3cfc65848ac80fa0db",
"assets/assets/images/noData.png": "de924641b98f4af0f0866a46c710a517",
"assets/assets/images/serverDown.png": "5590cf3d5dfcbb44a0f30d4b0a10f3ca",
"assets/assets/images/shipment.png": "0f8d8df1ee599ca6a2b3ae9ea8c94e1f",
"assets/assets/images/track.png": "d22f01ad75481168a8af473f635e6b93",
"assets/assets/images/zaitoonLogo.png": "d52e01398f0560f1248375b456eba29d",
"assets/FontManifest.json": "ec175ca9f2311c9c61b309d081935036",
"assets/fonts/MaterialIcons-Regular.otf": "61434bfb55acb14295162ba541e4b7d4",
"assets/NOTICES": "8c987f6d2c1fa53f0c594d420b878362",
"assets/packages/cupertino_icons/assets/CupertinoIcons.ttf": "33b7d9392238c04c131b6ce224e13711",
"assets/packages/flag/res/1x1/ac.svg": "2e2cd10142e0cac467738e92c1fa9af7",
"assets/packages/flag/res/1x1/ad.svg": "9805014a506f60bf6b94335e211bbcfb",
"assets/packages/flag/res/1x1/ae.svg": "45934bd6652bc182a651c708c36d7ac7",
"assets/packages/flag/res/1x1/af.svg": "1659d85d79f765b7c1517f366e940f42",
"assets/packages/flag/res/1x1/ag.svg": "bbe7c50a5163fb247bda879d98d0817a",
"assets/packages/flag/res/1x1/ai.svg": "c734089332d317c08ff90d332fb06ce5",
"assets/packages/flag/res/1x1/al.svg": "d9841087f4ebf16aacd1861e4349f21f",
"assets/packages/flag/res/1x1/am.svg": "0b3077cda7d27d864b3a4759d8e1c66d",
"assets/packages/flag/res/1x1/ao.svg": "f35cb95dfb4589229bcf7aa0e48f9cd4",
"assets/packages/flag/res/1x1/aq.svg": "6e6dec6b3ca522858e923ff9421e52c4",
"assets/packages/flag/res/1x1/ar.svg": "10c88aacb4222781116f6f7764f22f63",
"assets/packages/flag/res/1x1/as.svg": "72f99855a3d9f0f7d4889e40a0dc1dcd",
"assets/packages/flag/res/1x1/at.svg": "7e082ee8c92603a1e8c38cb2883d2b5e",
"assets/packages/flag/res/1x1/au.svg": "f1f354cbd7a9e4665bea302fd62e5508",
"assets/packages/flag/res/1x1/aw.svg": "0a812c0f2a3e1006ba709f2042daa539",
"assets/packages/flag/res/1x1/ax.svg": "56343e859566ae7ee86f6edf5968f410",
"assets/packages/flag/res/1x1/az.svg": "e2c9757873e837814bff04a5691acc87",
"assets/packages/flag/res/1x1/ba.svg": "5322ab1634fba4a65dd9adaf884a3849",
"assets/packages/flag/res/1x1/bb.svg": "c0872a439e17cb5265a8dd12d441502d",
"assets/packages/flag/res/1x1/bd.svg": "7663a5e8c666f370222147b91730bb50",
"assets/packages/flag/res/1x1/be.svg": "c68e875de561680df87edba2b72110a2",
"assets/packages/flag/res/1x1/bf.svg": "78ae214d5621bcebbb529c03ce2cb3f6",
"assets/packages/flag/res/1x1/bg.svg": "f858e2952e6571a85f509fe9c49c99ed",
"assets/packages/flag/res/1x1/bh.svg": "3ba3ccc9a239753157dc9f6a4d5cab64",
"assets/packages/flag/res/1x1/bi.svg": "88dd6f9a4e98b0b5967042884ce4c86e",
"assets/packages/flag/res/1x1/bj.svg": "bcec80726405917ba9aa20b0ab97e069",
"assets/packages/flag/res/1x1/bl.svg": "f6f0aa3927e3f93a67a528c4129ba7e5",
"assets/packages/flag/res/1x1/bm.svg": "5ffee91f3a2831aea18a1d5d8691496a",
"assets/packages/flag/res/1x1/bn.svg": "af4a89cfa3bc97296a69f3af688dc1b3",
"assets/packages/flag/res/1x1/bo.svg": "6292e53ef65cc44f2b37026b61137933",
"assets/packages/flag/res/1x1/bq.svg": "ca6053fe5ab237feb67db97f899093cd",
"assets/packages/flag/res/1x1/br.svg": "de53614600973f18433011f63b5921b5",
"assets/packages/flag/res/1x1/bs.svg": "06c00e24bc13af0fb0944c0973bb9afc",
"assets/packages/flag/res/1x1/bt.svg": "1df41f906b73952f3b4708d3d4031c83",
"assets/packages/flag/res/1x1/bv.svg": "4a141bcccc68bec915b82f392ef5cb9a",
"assets/packages/flag/res/1x1/bw.svg": "b02dc3e6711c34f56e9b2443ccd9aa3b",
"assets/packages/flag/res/1x1/by.svg": "352635824fcc67d923007df7fa236ada",
"assets/packages/flag/res/1x1/bz.svg": "1ef075458eb73dd367d7ccf51adb82cc",
"assets/packages/flag/res/1x1/ca.svg": "41f71048cc83088c6376ec49a576f838",
"assets/packages/flag/res/1x1/cc.svg": "cd88798aa74e8583b0221a2bc764d17d",
"assets/packages/flag/res/1x1/cd.svg": "34f965b1edc71c58963f5846d9a65212",
"assets/packages/flag/res/1x1/cefta.svg": "206a374eda6c6c618b6867e2a30c0b17",
"assets/packages/flag/res/1x1/cf.svg": "e5e17862517cb0fc54551d9e15526773",
"assets/packages/flag/res/1x1/cg.svg": "f1fcee499520ef8e2fa36cd644b207e1",
"assets/packages/flag/res/1x1/ch.svg": "3f922dffc2c0c6fe5d8715875fa8d613",
"assets/packages/flag/res/1x1/ci.svg": "db10b96d2aee962779e25693ec0bd8f9",
"assets/packages/flag/res/1x1/ck.svg": "4e06c054370d821506ca03906c4dc746",
"assets/packages/flag/res/1x1/cl.svg": "9b413a7fd684c6059c15c0b4c3a4eb40",
"assets/packages/flag/res/1x1/cm.svg": "a2e79045e868e032edc8da6925a5d756",
"assets/packages/flag/res/1x1/cn.svg": "c9bb029db37f0b950f358aa0c4109ec0",
"assets/packages/flag/res/1x1/co.svg": "4d7fd81787d380952325948041a11f55",
"assets/packages/flag/res/1x1/cp.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/cr.svg": "5aaae72afb805b23eb8d5e4a5154577d",
"assets/packages/flag/res/1x1/cu.svg": "548dbed4d21345ecea247c6615982570",
"assets/packages/flag/res/1x1/cv.svg": "e592e2e94c56b4c48f305b9b4709d74a",
"assets/packages/flag/res/1x1/cw.svg": "35fd31675c3090956978a7d68bf5740c",
"assets/packages/flag/res/1x1/cx.svg": "3844d69c7aa08d8eae71fa8c57e7dfbb",
"assets/packages/flag/res/1x1/cy.svg": "3870deb88b2ad472ebe098a72a4c2c8a",
"assets/packages/flag/res/1x1/cz.svg": "3640c0bc948590246a5e04b7dea57592",
"assets/packages/flag/res/1x1/de.svg": "2f0cc91e5d105e0b85191991f57a4345",
"assets/packages/flag/res/1x1/dg.svg": "a54f480a3a52d1ea899c2a6b8a70419f",
"assets/packages/flag/res/1x1/dj.svg": "2581f6c6c2fe3360c287b99ae898f63f",
"assets/packages/flag/res/1x1/dk.svg": "95d7653a1b39adcdb3b461d5bca9243d",
"assets/packages/flag/res/1x1/dm.svg": "a75393c499f9c7e49b424920e662cb6d",
"assets/packages/flag/res/1x1/do.svg": "7500e0054c45911ac9b16be135fb61b6",
"assets/packages/flag/res/1x1/dz.svg": "443888f8d134996827f91a2dd76f1081",
"assets/packages/flag/res/1x1/ea.svg": "f1f28508bbfc0325ac332b2a798d3320",
"assets/packages/flag/res/1x1/ec.svg": "d11fdd8fb8591fd696707d9632b66685",
"assets/packages/flag/res/1x1/ee.svg": "156f785964685f91fe5197e572baa30a",
"assets/packages/flag/res/1x1/eg.svg": "00bd0a735532c3fca068a8dbe5a82709",
"assets/packages/flag/res/1x1/eh.svg": "af3192dd0e85e1ff645ffa0c34a93f7c",
"assets/packages/flag/res/1x1/er.svg": "c4840d0b09163bdcf14295840685b5b2",
"assets/packages/flag/res/1x1/es.svg": "f1f28508bbfc0325ac332b2a798d3320",
"assets/packages/flag/res/1x1/es_ct.svg": "c9ebf90e8c160c20d66b4376df29fbd7",
"assets/packages/flag/res/1x1/es_ga.svg": "737f6b913361d773bdfbc5dc19ad31fd",
"assets/packages/flag/res/1x1/et.svg": "fc95a1a7d1beb97c3a37a5b94ba59400",
"assets/packages/flag/res/1x1/eu.svg": "047356eee2965780de44dd47e204f336",
"assets/packages/flag/res/1x1/fi.svg": "348f1ca3773ade2d8dd0f9dd3d4df639",
"assets/packages/flag/res/1x1/fj.svg": "f373f1d0cdb2b482e8c8c4421c9f5dec",
"assets/packages/flag/res/1x1/fk.svg": "32830e8f91ed02aa7970bb7d83d923d0",
"assets/packages/flag/res/1x1/fm.svg": "9931c09840d6c80c83f791fa6f6d2bb7",
"assets/packages/flag/res/1x1/fo.svg": "bb432c56e24178a5fe0e4db96c963637",
"assets/packages/flag/res/1x1/fr.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/ga.svg": "f6d2c4c84e735580c44699958b33a284",
"assets/packages/flag/res/1x1/gb.svg": "d58c15ec65d1a426102a804a18006aed",
"assets/packages/flag/res/1x1/gb_eng.svg": "c8febd281aa32c6ec452dd492910337e",
"assets/packages/flag/res/1x1/gb_nir.svg": "3fa00378e757645539304003481af897",
"assets/packages/flag/res/1x1/gb_sct.svg": "1b6628ebbb1c84aabe005bcbf7440ff4",
"assets/packages/flag/res/1x1/gb_wls.svg": "d01c14f04104086361e03b54a508c4f9",
"assets/packages/flag/res/1x1/gd.svg": "ac24dddf5c6389cde05690ee178582c9",
"assets/packages/flag/res/1x1/ge.svg": "8f492858b8d729c6482d21e8a4c36c15",
"assets/packages/flag/res/1x1/gf.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/gg.svg": "927c9394bfe714673a0b267d0319b1fc",
"assets/packages/flag/res/1x1/gh.svg": "fdafce6720d5e67c5e86c30afd4319aa",
"assets/packages/flag/res/1x1/gi.svg": "e0c1cece73679cc8929dde63fbb6a8e0",
"assets/packages/flag/res/1x1/gl.svg": "a6ea3fff4732df1ebd57d44d1dcc7663",
"assets/packages/flag/res/1x1/gm.svg": "446ef2c9b48f29a0cfcb3d92ed33a125",
"assets/packages/flag/res/1x1/gn.svg": "276d2a57defe35e8c45a8dbb272a520f",
"assets/packages/flag/res/1x1/gp.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/gq.svg": "1a073d84805235973890b87735dc2e52",
"assets/packages/flag/res/1x1/gr.svg": "71d156a971a9498b8a423f8e6cbba323",
"assets/packages/flag/res/1x1/gs.svg": "ba954943b2065bbffbc5e402b4e87991",
"assets/packages/flag/res/1x1/gt.svg": "4bed64092f568e112e0a993cc3aad59b",
"assets/packages/flag/res/1x1/gu.svg": "dad46379bd1711d94da396e99d708319",
"assets/packages/flag/res/1x1/gw.svg": "a56c77836022197e366471d9e2c3208a",
"assets/packages/flag/res/1x1/gy.svg": "86b6394d8ed8618de2da0eff41937323",
"assets/packages/flag/res/1x1/hk.svg": "97558ea1d0849bba142999592cf33484",
"assets/packages/flag/res/1x1/hm.svg": "f1f354cbd7a9e4665bea302fd62e5508",
"assets/packages/flag/res/1x1/hn.svg": "2c4550b532c44161a976dbdff1e3d6bc",
"assets/packages/flag/res/1x1/hr.svg": "ed126951164623a22270634ca4ddb518",
"assets/packages/flag/res/1x1/ht.svg": "cd93ae05ea77203aa2701e3a83fd2bc0",
"assets/packages/flag/res/1x1/hu.svg": "67a978cb74f62eb3fd6c68c545fbcab0",
"assets/packages/flag/res/1x1/ic.svg": "f7be83edbb3b26d33af651224b748376",
"assets/packages/flag/res/1x1/id.svg": "031934870aa286bc40befaed19205a62",
"assets/packages/flag/res/1x1/ie.svg": "6aef1b52eb166ecf601489a3b456d69b",
"assets/packages/flag/res/1x1/il.svg": "dcb67965bcf807ddfeee7bd681266db7",
"assets/packages/flag/res/1x1/im.svg": "ad6346a72cec9b5f40dd43e6fba8d2fa",
"assets/packages/flag/res/1x1/in.svg": "81fe58f927bd6c5d1e294889cb831789",
"assets/packages/flag/res/1x1/io.svg": "a54f480a3a52d1ea899c2a6b8a70419f",
"assets/packages/flag/res/1x1/iq.svg": "961052851e89ede67f7485231c9d2d88",
"assets/packages/flag/res/1x1/ir.svg": "22eeae4df9e21ba101dbb553ab302af2",
"assets/packages/flag/res/1x1/is.svg": "88840668b90a4119f19fc1b6423cc95a",
"assets/packages/flag/res/1x1/it.svg": "6ea5e91a6f11dcc40fe1cb4806b4fa09",
"assets/packages/flag/res/1x1/je.svg": "7080de3e790f9d91fad7353713a1a7a0",
"assets/packages/flag/res/1x1/jm.svg": "445c2e19aab50502be1f56982ff9ec11",
"assets/packages/flag/res/1x1/jo.svg": "4390a9df3e1c4bcebd3e1ede76481197",
"assets/packages/flag/res/1x1/jp.svg": "64d97fd861ac82d4a46bfe1204d6d6e7",
"assets/packages/flag/res/1x1/ke.svg": "f6af675b756906bff7dcf04098315bd5",
"assets/packages/flag/res/1x1/kg.svg": "d8b5a4a88594e2eb99021dfc655d2837",
"assets/packages/flag/res/1x1/kh.svg": "bf1580194c3d49b5b9747b656397ad50",
"assets/packages/flag/res/1x1/ki.svg": "977da2350650d2d1bff251c40e4f9596",
"assets/packages/flag/res/1x1/km.svg": "958b22c3664cf1e887b63c04e08c5f3e",
"assets/packages/flag/res/1x1/kn.svg": "1d1b4cd7f01a792dbe6522892ddc7f8b",
"assets/packages/flag/res/1x1/kp.svg": "7173ca13913d6330c06e5ff9ada1cd9b",
"assets/packages/flag/res/1x1/kr.svg": "fe434cdd35ea00f883c1a8a7b62f75f3",
"assets/packages/flag/res/1x1/kw.svg": "051b70187e805eea9cf6bcb4c1ce9157",
"assets/packages/flag/res/1x1/ky.svg": "f9bd1d2599951cf55222e12ddce8f616",
"assets/packages/flag/res/1x1/kz.svg": "610be97ea8d6c164a21aa78a4d5daddc",
"assets/packages/flag/res/1x1/la.svg": "173ac09dac181fc02cc44b5c834af92a",
"assets/packages/flag/res/1x1/lb.svg": "61043708cfe7b4023c3774f2977f2c33",
"assets/packages/flag/res/1x1/lc.svg": "51df06928e6354af954dd613e6ba65bf",
"assets/packages/flag/res/1x1/li.svg": "423432f3aa5f11b7338de53178c2554f",
"assets/packages/flag/res/1x1/lk.svg": "ad2bf24f332bd6856a07b7c557d1d5ff",
"assets/packages/flag/res/1x1/lr.svg": "f5860d8abf9b5861e409ac1b5027dcef",
"assets/packages/flag/res/1x1/ls.svg": "16869dd0b8a8c4856a092bb964a04f39",
"assets/packages/flag/res/1x1/lt.svg": "2edceee2dd97b99f6bdfd875aef0d360",
"assets/packages/flag/res/1x1/lu.svg": "0bec8d8207fa7ab778d8aef1bffb304a",
"assets/packages/flag/res/1x1/lv.svg": "e0edd30ddcb307d63a74ea5b49fa0a7e",
"assets/packages/flag/res/1x1/ly.svg": "d54111d97c05b48afcf04fb3f45dea64",
"assets/packages/flag/res/1x1/ma.svg": "8a48afb84bb2e72326ddc5497af544af",
"assets/packages/flag/res/1x1/mc.svg": "e2a68798019959e70ec8ef8e70fca58d",
"assets/packages/flag/res/1x1/md.svg": "245f3f7be100abd2fc86a77cbccc6ec0",
"assets/packages/flag/res/1x1/me.svg": "5e1048ae9c8f3d856d39a9f325a185a9",
"assets/packages/flag/res/1x1/mf.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/mg.svg": "2fb9f56d1e33c999fd32bf5fbe05b4a9",
"assets/packages/flag/res/1x1/mh.svg": "be806329405e56c79e0f1ab219f12d67",
"assets/packages/flag/res/1x1/mk.svg": "d981cf6b1899f875825bb9d6e171236d",
"assets/packages/flag/res/1x1/ml.svg": "e6fbf0c245782de99a46092b940f28ad",
"assets/packages/flag/res/1x1/mm.svg": "cf4539436e04efa38d96d145914f7592",
"assets/packages/flag/res/1x1/mn.svg": "bfa4231b9d6ae06e8dddcd63302d0b89",
"assets/packages/flag/res/1x1/mo.svg": "77314c7df91a7649a566cc69eeb8c03c",
"assets/packages/flag/res/1x1/mp.svg": "2f5ac5a0ea6207544f885bb7c66d761f",
"assets/packages/flag/res/1x1/mq.svg": "f6f0aa3927e3f93a67a528c4129ba7e5",
"assets/packages/flag/res/1x1/mr.svg": "c2eca63d51c621f0016a93ea92f24dc2",
"assets/packages/flag/res/1x1/ms.svg": "acfcb0042f82ea0b662e9c4c3e948e90",
"assets/packages/flag/res/1x1/mt.svg": "334ad7686d010528ca46298b891bc901",
"assets/packages/flag/res/1x1/mu.svg": "b70e5c5b44d660b3bbc602624926b050",
"assets/packages/flag/res/1x1/mv.svg": "8569fbeba8c16431780300982997a330",
"assets/packages/flag/res/1x1/mw.svg": "8f9587f5d16dba2caf92217d501836dc",
"assets/packages/flag/res/1x1/mx.svg": "6759bedd0e6ace7da7594071998a0d52",
"assets/packages/flag/res/1x1/my.svg": "78a9a57d5bcba8d1094b74416d837a0e",
"assets/packages/flag/res/1x1/mz.svg": "e0ebb2b90690be3b86d0e8cf7024b049",
"assets/packages/flag/res/1x1/na.svg": "3e53a03e2dc9a11d751cf0863302ba34",
"assets/packages/flag/res/1x1/nc.svg": "8c2eb493e0291925be73ce90d9c09cf3",
"assets/packages/flag/res/1x1/ne.svg": "65a7fb93b2401113ae4e46010906a9ff",
"assets/packages/flag/res/1x1/nf.svg": "99afb103dceb393107cbd79903a650a1",
"assets/packages/flag/res/1x1/ng.svg": "067e966e15360a9850dde8baa74b7d49",
"assets/packages/flag/res/1x1/ni.svg": "2188314c4f1c1ebc5fe79b115c4f3220",
"assets/packages/flag/res/1x1/nl.svg": "ca6053fe5ab237feb67db97f899093cd",
"assets/packages/flag/res/1x1/no.svg": "36405c4e91fad11c039cbb826c0969db",
"assets/packages/flag/res/1x1/np.svg": "217393db4ceb2df44c65cd4d54b345ab",
"assets/packages/flag/res/1x1/nr.svg": "a30c02f1448adc3f1941f59bb942f387",
"assets/packages/flag/res/1x1/nu.svg": "146d73987426fc252a8cc92f55e76e5e",
"assets/packages/flag/res/1x1/nz.svg": "98bfc58cdb6ff872e80d70e0e08ddf2f",
"assets/packages/flag/res/1x1/om.svg": "8987712514669da4af1049d810062723",
"assets/packages/flag/res/1x1/pa.svg": "2fab7977984879ec25e1a0ec2395928a",
"assets/packages/flag/res/1x1/pe.svg": "6b5a31b657d5218411d3b7afc6ea6ec6",
"assets/packages/flag/res/1x1/pf.svg": "8e82b1ce8fb48f74769b9f19b8edac14",
"assets/packages/flag/res/1x1/pg.svg": "bbb1dc0698c1772d99a456e04cd9a6d4",
"assets/packages/flag/res/1x1/ph.svg": "d3a27e97cce13ff399a14c713b56b2f3",
"assets/packages/flag/res/1x1/pk.svg": "325dccb4a38b8de6f73f5e45d16740a1",
"assets/packages/flag/res/1x1/pl.svg": "ab47fed8df8355569d7b6962ce7cdbd9",
"assets/packages/flag/res/1x1/pm.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/pn.svg": "167cb8c1d6f28c8c84a2bfbb9ea3c4b7",
"assets/packages/flag/res/1x1/pr.svg": "26af236d35691cc13bbb56fa376de0eb",
"assets/packages/flag/res/1x1/ps.svg": "c02e604245ce170277f09ccdb7c99174",
"assets/packages/flag/res/1x1/pt.svg": "cca0388ebb7f0eea83bb56e0451c1710",
"assets/packages/flag/res/1x1/pw.svg": "ca9326c7ff0225479695567c0b7b3ee3",
"assets/packages/flag/res/1x1/py.svg": "8f92d259be9cecd58ea9738eb45d68f5",
"assets/packages/flag/res/1x1/qa.svg": "80b4995371e82e76f7eb8bcb661903ff",
"assets/packages/flag/res/1x1/re.svg": "f6f0aa3927e3f93a67a528c4129ba7e5",
"assets/packages/flag/res/1x1/ro.svg": "b4d34f77d332690584eaa24bb35ac018",
"assets/packages/flag/res/1x1/rs.svg": "af45208ee5c54fd349fa99f64656e234",
"assets/packages/flag/res/1x1/ru.svg": "e86978e3eac5f4b7ee872d2e5bb0abc5",
"assets/packages/flag/res/1x1/rw.svg": "96be0b427f3fbd69b70f77aa06e2c8d6",
"assets/packages/flag/res/1x1/sa.svg": "47912a457a8770924985afb826bcc5ef",
"assets/packages/flag/res/1x1/sb.svg": "121cf2180300b9cf51b0e49e243b149e",
"assets/packages/flag/res/1x1/sc.svg": "3f9ae692351561df82cfdc5551506eec",
"assets/packages/flag/res/1x1/sd.svg": "5bcdf95605c5249b8951ecf9ea5dde05",
"assets/packages/flag/res/1x1/se.svg": "de9ca5a658b8a84ce4dc7c7fb9e78784",
"assets/packages/flag/res/1x1/sg.svg": "105e4da87a4f4625738043e511652753",
"assets/packages/flag/res/1x1/sh.svg": "2e2cd10142e0cac467738e92c1fa9af7",
"assets/packages/flag/res/1x1/si.svg": "27b82cac994cfe1b9384b507641fc6b5",
"assets/packages/flag/res/1x1/sj.svg": "670cac9b6290af913dd9b18993df0f7a",
"assets/packages/flag/res/1x1/sk.svg": "b19ede46134bf7d7385bd1bc5a490f23",
"assets/packages/flag/res/1x1/sl.svg": "fe58f34973fe80a2a13bf80c22ebd939",
"assets/packages/flag/res/1x1/sm.svg": "129322158e1403854c4bdc2818aee113",
"assets/packages/flag/res/1x1/sn.svg": "940da6c136b5eafdc7e4bf640ddafcb6",
"assets/packages/flag/res/1x1/so.svg": "722499dc96841318664f657388c9c081",
"assets/packages/flag/res/1x1/sr.svg": "bafb645d404bbd1932702c4f426071e8",
"assets/packages/flag/res/1x1/ss.svg": "5cbc8aaa5b275cf3c24e0b20b3c2d070",
"assets/packages/flag/res/1x1/st.svg": "78c00df86bf51be44357191e441bc9df",
"assets/packages/flag/res/1x1/sv.svg": "b7cac09b53d819525e1b4ceba6c3fe84",
"assets/packages/flag/res/1x1/sx.svg": "c9adf0caca2fa8a1d33171eacbf49b3c",
"assets/packages/flag/res/1x1/sy.svg": "654135cd410c03a04c4c1707dc11bc01",
"assets/packages/flag/res/1x1/sz.svg": "13848c1ecf7ab8d17181c1c36bc5b2a3",
"assets/packages/flag/res/1x1/ta.svg": "2e2cd10142e0cac467738e92c1fa9af7",
"assets/packages/flag/res/1x1/tc.svg": "982653ea77e4318493b3a4328ff487b3",
"assets/packages/flag/res/1x1/td.svg": "1df7f13006f852e2d6d4ff5fe7a409b3",
"assets/packages/flag/res/1x1/tf.svg": "a2ec6f5b6dd0eba28f3677a64ce9b274",
"assets/packages/flag/res/1x1/tg.svg": "b10d17540532ffe650cf4df44a495588",
"assets/packages/flag/res/1x1/th.svg": "d508f2a880f05270426dc157cc0ebc15",
"assets/packages/flag/res/1x1/tj.svg": "254b75848d839572d698fed2fecc0fea",
"assets/packages/flag/res/1x1/tk.svg": "d564405fa8f815c7ea21818c87f94624",
"assets/packages/flag/res/1x1/tl.svg": "25b88553261bd64df52a3ae5b654f24e",
"assets/packages/flag/res/1x1/tm.svg": "ce26edee336cada057e4e916f90a5bc3",
"assets/packages/flag/res/1x1/tn.svg": "e546d83364524cc92e03ee9f6b3cf4ae",
"assets/packages/flag/res/1x1/to.svg": "636d255446affe55581fd0fce507249d",
"assets/packages/flag/res/1x1/tr.svg": "c88c74846082ded67bb1f6c1eafdc6fb",
"assets/packages/flag/res/1x1/tt.svg": "5fe8004f8bccda8f477ba16b6b2df75b",
"assets/packages/flag/res/1x1/tv.svg": "1ad85679785edde377ef707ac15621e2",
"assets/packages/flag/res/1x1/tw.svg": "0bf37e74116e5b04b4d32c69496fc1aa",
"assets/packages/flag/res/1x1/tz.svg": "328385bdc876ebd2f5af5c34bd140006",
"assets/packages/flag/res/1x1/ua.svg": "e27ef6698234adc2e9671644191e965e",
"assets/packages/flag/res/1x1/ug.svg": "79d95ff5c4e9d4903e0e9c247703a02f",
"assets/packages/flag/res/1x1/um.svg": "917fc662091270038e4baa04e19550ed",
"assets/packages/flag/res/1x1/un.svg": "db9af2f29079b92db29608b1c2d32820",
"assets/packages/flag/res/1x1/us.svg": "2951242f92863c26072b25acc10931a6",
"assets/packages/flag/res/1x1/uy.svg": "fb7e8b7324ac951068525971c30f2a57",
"assets/packages/flag/res/1x1/uz.svg": "8bb8fede03648b33cdc06041c8edab61",
"assets/packages/flag/res/1x1/va.svg": "2b55f73a43f079030dca1b078c37b131",
"assets/packages/flag/res/1x1/vc.svg": "6ce5e58721a0b004c1dfc4eb5515b8d8",
"assets/packages/flag/res/1x1/ve.svg": "9f8c0fe820af1ba6924ccdee86f2e123",
"assets/packages/flag/res/1x1/vg.svg": "fde0ab96bf410acedba335d03ef57b36",
"assets/packages/flag/res/1x1/vi.svg": "c2238d6ed7ef59ba47e9f9cff5084029",
"assets/packages/flag/res/1x1/vn.svg": "7dea19dbcb0678d46b48dfb47327eebb",
"assets/packages/flag/res/1x1/vu.svg": "b440c9e1b97daa004bb01f7172e8f145",
"assets/packages/flag/res/1x1/wf.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/ws.svg": "2a5555ffe74477eda22d01ddb5de1fb2",
"assets/packages/flag/res/1x1/xk.svg": "ed6afdc55a0ebe1d700417ff38ba04dd",
"assets/packages/flag/res/1x1/xx.svg": "d4dde5a108b953be76684eaf0497e5cb",
"assets/packages/flag/res/1x1/ye.svg": "f3180ed192f8a84a22abe0c38da01d65",
"assets/packages/flag/res/1x1/yt.svg": "bc0519b23ed8c78fcdd68c54bb54cc87",
"assets/packages/flag/res/1x1/za.svg": "3ee31b6d1f7d214566af589a45c61f89",
"assets/packages/flag/res/1x1/zm.svg": "8c8d64b17c16fdbce8c9dfd1619734af",
"assets/packages/flag/res/1x1/zw.svg": "efd1382e22f338522deb569f0cf4f88c",
"assets/packages/flag/res/4x3/ac.svg": "efa815b4037fd93505f4a11f337da614",
"assets/packages/flag/res/4x3/ad.svg": "d59d8fb4171b8f0c3065ada743d20616",
"assets/packages/flag/res/4x3/ae.svg": "6ab1227cbf8295d20ed9fc79f19f955b",
"assets/packages/flag/res/4x3/af.svg": "5e159ac5e2bec46cf36e1d99ec5324d4",
"assets/packages/flag/res/4x3/ag.svg": "4886a575aded958dd49f73723bcb6f08",
"assets/packages/flag/res/4x3/ai.svg": "2a29ec4ac0b88b1081379f03601c0032",
"assets/packages/flag/res/4x3/al.svg": "18ca6658a7f3e7647124f72d4ed47c53",
"assets/packages/flag/res/4x3/am.svg": "690fe24e2142e290d3d9263ca007482f",
"assets/packages/flag/res/4x3/ao.svg": "3338af0a10cde1c6bb607740e4b26b98",
"assets/packages/flag/res/4x3/aq.svg": "dcc9eb1b226c03719df57a0a5e9db0ce",
"assets/packages/flag/res/4x3/ar.svg": "de93d2b5fd7c8a4cdad76ca20002a2c1",
"assets/packages/flag/res/4x3/as.svg": "7631d014e7f97b09dedbc0f76e0b667e",
"assets/packages/flag/res/4x3/at.svg": "d0e0c3e36cba9a90ab2e30c6ec4f0f28",
"assets/packages/flag/res/4x3/au.svg": "5b9eed902c75ae853e71ecf5dbecec48",
"assets/packages/flag/res/4x3/aw.svg": "3454c329106c91498ed1ed77b51eb269",
"assets/packages/flag/res/4x3/ax.svg": "c93d27c19e7f1780b3ccb255c33e1f3c",
"assets/packages/flag/res/4x3/az.svg": "a4e3c1d5a85484af8858a4a411608605",
"assets/packages/flag/res/4x3/ba.svg": "8868eb7b55a8ebedf2b7e99d123a4f2b",
"assets/packages/flag/res/4x3/bb.svg": "e68c1240cecb9d976d89e91ad1c08991",
"assets/packages/flag/res/4x3/bd.svg": "7f0542aeacf8930f23f67ee4e4ee2973",
"assets/packages/flag/res/4x3/be.svg": "eb7f244335b0a2370bf1ac786b09960a",
"assets/packages/flag/res/4x3/bf.svg": "7c02c502e4a9f766564c68aa5cec03ef",
"assets/packages/flag/res/4x3/bg.svg": "eb1a4137877013e70178c27184cf3f2d",
"assets/packages/flag/res/4x3/bh.svg": "d28a5c0e15b278d9489d9eb95b7f6095",
"assets/packages/flag/res/4x3/bi.svg": "36723fa2fa1bcb89d031f23a765b8b7b",
"assets/packages/flag/res/4x3/bj.svg": "56c90ddc6947e518a1b8a9775cdf84c6",
"assets/packages/flag/res/4x3/bl.svg": "b3f6d471c9eef4e50b9dd9f3327cd2d2",
"assets/packages/flag/res/4x3/bm.svg": "907c9bbd252853ef585904d5adec3a44",
"assets/packages/flag/res/4x3/bn.svg": "aa612a8871b227059fd9ef95f6710dcc",
"assets/packages/flag/res/4x3/bo.svg": "22d85579078d03a50a11b23887a4f438",
"assets/packages/flag/res/4x3/bq.svg": "caec7df85906eb333b4beef973037364",
"assets/packages/flag/res/4x3/br.svg": "5e520b6abb2d15f79021b5d007ffd9ff",
"assets/packages/flag/res/4x3/bs.svg": "6df5012f13c007588d3ee7c1ef1b162b",
"assets/packages/flag/res/4x3/bt.svg": "eef6852266a664b6eb739b3152fdcda3",
"assets/packages/flag/res/4x3/bv.svg": "29b1b23c88e7871ca8a7ecd3d1dc7835",
"assets/packages/flag/res/4x3/bw.svg": "4268276c0949609c28e3a5b4772531d7",
"assets/packages/flag/res/4x3/by.svg": "10e988d982ea62d690be2fa0d665d96f",
"assets/packages/flag/res/4x3/bz.svg": "998698f9e16bc2a29477eb1542895475",
"assets/packages/flag/res/4x3/ca.svg": "0b6421699b46923ae6c6494c0728949f",
"assets/packages/flag/res/4x3/cc.svg": "e3789670fcd12238ea52856f933662bb",
"assets/packages/flag/res/4x3/cd.svg": "55d25cb0faa31cd03e9435bb6acf6807",
"assets/packages/flag/res/4x3/cefta.svg": "f6c6757f3d70f9fbb205601081285f93",
"assets/packages/flag/res/4x3/cf.svg": "7784c2f4503232b571664c9202758ba2",
"assets/packages/flag/res/4x3/cg.svg": "18a4aa48d11bcd7937ec66b92912ed99",
"assets/packages/flag/res/4x3/ch.svg": "994eb831b34f159d98a3aa3d75597128",
"assets/packages/flag/res/4x3/ci.svg": "05e8ff32d86f6683eab575f8b420efeb",
"assets/packages/flag/res/4x3/ck.svg": "2e17c4b2a25767cd8f32d1b73c59f452",
"assets/packages/flag/res/4x3/cl.svg": "804bbe1658a51d692211d878309541ec",
"assets/packages/flag/res/4x3/cm.svg": "e17af624fc6f7861c62460f5be017415",
"assets/packages/flag/res/4x3/cn.svg": "41626b8f83b71fef765f0e299fa36634",
"assets/packages/flag/res/4x3/co.svg": "b2e48c0d597e1a5a1777b980d079272b",
"assets/packages/flag/res/4x3/cp.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/cr.svg": "4e3b0ab5334661378790710feef1af00",
"assets/packages/flag/res/4x3/cu.svg": "cd54c7b70bd197e87b4eefa074a6c91a",
"assets/packages/flag/res/4x3/cv.svg": "ad04395a3a8b6e55be2334f488c51a72",
"assets/packages/flag/res/4x3/cw.svg": "c169cb896bcb346b133e888580afed83",
"assets/packages/flag/res/4x3/cx.svg": "033c6471acf0046ca221102a5da161a9",
"assets/packages/flag/res/4x3/cy.svg": "244065a99624f062e13fd43636fed071",
"assets/packages/flag/res/4x3/cz.svg": "f479bb2b10537acf4311993d80bad4f6",
"assets/packages/flag/res/4x3/de.svg": "52f420da94ae60f6dcf173d224e365d8",
"assets/packages/flag/res/4x3/dg.svg": "c25e233bffb37c770b58261108308890",
"assets/packages/flag/res/4x3/dj.svg": "627a4b94852bd38441a8da1efd135454",
"assets/packages/flag/res/4x3/dk.svg": "6c007494b43b9fd94b1c5bf1639e43a9",
"assets/packages/flag/res/4x3/dm.svg": "b09e5d5bfa99fb4c8e275fc7f7c26acc",
"assets/packages/flag/res/4x3/do.svg": "b41b58a03a285620f6b63a66e1401364",
"assets/packages/flag/res/4x3/dz.svg": "dcbdf905a4fd8235ed46d03650fbbb4f",
"assets/packages/flag/res/4x3/ea.svg": "0d18d09009a042e363091144a97534cd",
"assets/packages/flag/res/4x3/ec.svg": "b38c413e53a93212cffacf43f78444f3",
"assets/packages/flag/res/4x3/ee.svg": "3b36f2c09279aebc5662f3fbdc4eb8d3",
"assets/packages/flag/res/4x3/eg.svg": "ed20f109e5ee34e0e3d323f40188c688",
"assets/packages/flag/res/4x3/eh.svg": "2c3a3d541d1b6b9d0c7004e64b718b10",
"assets/packages/flag/res/4x3/er.svg": "c1bc13113d4b13fb8646e8b40063a0ed",
"assets/packages/flag/res/4x3/es.svg": "0d18d09009a042e363091144a97534cd",
"assets/packages/flag/res/4x3/es_ct.svg": "7e150c011a2a54e79cc37e4168333b42",
"assets/packages/flag/res/4x3/es_ga.svg": "1013629dca544086d0945780c3b706e0",
"assets/packages/flag/res/4x3/et.svg": "bf72ca743e5641a614bc73666f965a4d",
"assets/packages/flag/res/4x3/eu.svg": "f0e7d8c48cda0cf46aca4fdfaa49ac54",
"assets/packages/flag/res/4x3/fi.svg": "b407a543a3d7f815a2d0e2591123dc3a",
"assets/packages/flag/res/4x3/fj.svg": "f10d22932c54b124bc991f29ed32e8f8",
"assets/packages/flag/res/4x3/fk.svg": "72f86c11218d8cb95f12b753993ec7e9",
"assets/packages/flag/res/4x3/fm.svg": "8fbdf4832eee8bec0b6ac270b9932b7b",
"assets/packages/flag/res/4x3/fo.svg": "35f7a1f6fff19e3f4c1452dc6b557d27",
"assets/packages/flag/res/4x3/fr.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/ga.svg": "b1638d55a2bf23acde00cf21b1162428",
"assets/packages/flag/res/4x3/gb.svg": "0138f381482a6bafeb2299991e716db7",
"assets/packages/flag/res/4x3/gb_eng.svg": "d42077ca50194a3d9f772414752d882f",
"assets/packages/flag/res/4x3/gb_nir.svg": "977a634e4c71adc27b9c76a0a9ed8b3d",
"assets/packages/flag/res/4x3/gb_sct.svg": "e72c15f7919e6989c8932f3e78a45dea",
"assets/packages/flag/res/4x3/gb_wls.svg": "16e72a91372cabcdf5c889d44316e9ba",
"assets/packages/flag/res/4x3/gd.svg": "efba50734718ecd92dcb9480a60f41f6",
"assets/packages/flag/res/4x3/ge.svg": "728758e4c47c50ad069b29f909b5afc6",
"assets/packages/flag/res/4x3/gf.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/gg.svg": "743de8c3b6e1d7b1918125847f5bcb90",
"assets/packages/flag/res/4x3/gh.svg": "f7e7e2a124766a36fae8a143a50b589c",
"assets/packages/flag/res/4x3/gi.svg": "88d7a95af4fcd4bce1aa84a8866af7ee",
"assets/packages/flag/res/4x3/gl.svg": "05eb77f4a6a36ac4c8bcb15e1d92a801",
"assets/packages/flag/res/4x3/gm.svg": "36b078f4cc26952c27ca94e4f429cf6b",
"assets/packages/flag/res/4x3/gn.svg": "4152afbef9d26c92663b5006ea4967bb",
"assets/packages/flag/res/4x3/gp.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/gq.svg": "3b8e35fb6a534c8df570c309ac266949",
"assets/packages/flag/res/4x3/gr.svg": "dd5e463f05a275aaa2e07ceadd095a56",
"assets/packages/flag/res/4x3/gs.svg": "b948dfeef820e2909b1a9b8865c52042",
"assets/packages/flag/res/4x3/gt.svg": "927cbe1f85ccfc243623ab1208777665",
"assets/packages/flag/res/4x3/gu.svg": "1ef8fab87461677b2bae653f3692e9f8",
"assets/packages/flag/res/4x3/gw.svg": "5246b3fb6e82964ffd13a2278a74cbb8",
"assets/packages/flag/res/4x3/gy.svg": "cd438d4a8ce112fbb487afdcd624361b",
"assets/packages/flag/res/4x3/hk.svg": "3519044c0f06deba8a16aa08099ac1c1",
"assets/packages/flag/res/4x3/hm.svg": "5b9eed902c75ae853e71ecf5dbecec48",
"assets/packages/flag/res/4x3/hn.svg": "08ac629402a25f406de910003b5ed103",
"assets/packages/flag/res/4x3/hr.svg": "1ccc7ad449fded8a2a63f4d85e984132",
"assets/packages/flag/res/4x3/ht.svg": "ac50589a11255a49ee3db846ec168467",
"assets/packages/flag/res/4x3/hu.svg": "44bb640d57ed03c244c60b8f34b179fd",
"assets/packages/flag/res/4x3/ic.svg": "20122b99c3e10689551eb360cf30214e",
"assets/packages/flag/res/4x3/id.svg": "ca928481df052c28404665402e42e136",
"assets/packages/flag/res/4x3/ie.svg": "746ccd13c44855e448495a2f54ef3f39",
"assets/packages/flag/res/4x3/il.svg": "6a90a7e28b2c77c4c049c41e11788e58",
"assets/packages/flag/res/4x3/im.svg": "5b91c04a8726a0c5739922cc09a0189a",
"assets/packages/flag/res/4x3/in.svg": "24090d9d791e4b5429442ef686fc3e0f",
"assets/packages/flag/res/4x3/io.svg": "c25e233bffb37c770b58261108308890",
"assets/packages/flag/res/4x3/iq.svg": "7c79ba53a69780e119142ec8b7396115",
"assets/packages/flag/res/4x3/ir.svg": "9e66fa8c5857212eb3dd98b6308b0e3a",
"assets/packages/flag/res/4x3/is.svg": "2fe273b9e3d90954434c55b50f04ce91",
"assets/packages/flag/res/4x3/it.svg": "732510493cc43c0e0097b7c0683813f6",
"assets/packages/flag/res/4x3/je.svg": "b0a9d42764b369e8472b91e0b4f64842",
"assets/packages/flag/res/4x3/jm.svg": "5a7cbd8ae075e5840bb6fdd11769f6a0",
"assets/packages/flag/res/4x3/jo.svg": "cc2572d6f8d57eb4443fb2fa77e3d8d0",
"assets/packages/flag/res/4x3/jp.svg": "2272c8ce4d079a06fdbc16defdef5c7c",
"assets/packages/flag/res/4x3/ke.svg": "aea7a9317d29689311dbf8efaa515478",
"assets/packages/flag/res/4x3/kg.svg": "e7dbc4db6dc25ed206a82a86563a9ea8",
"assets/packages/flag/res/4x3/kh.svg": "ba2688e6c48d7fd861e6cabd609ec391",
"assets/packages/flag/res/4x3/ki.svg": "39557bfa0383fb750024e7c77a8dd791",
"assets/packages/flag/res/4x3/km.svg": "c500d09f16493c91b1b3e3f8739d4c26",
"assets/packages/flag/res/4x3/kn.svg": "06d8b2d58b0eee64460c27765df28fac",
"assets/packages/flag/res/4x3/kp.svg": "a951d289b91278d7cf6135a5dd266eb0",
"assets/packages/flag/res/4x3/kr.svg": "c55736e2bb0263d74e1d16eb3970529c",
"assets/packages/flag/res/4x3/kw.svg": "82443084834343fc38fa7d792a0b2eff",
"assets/packages/flag/res/4x3/ky.svg": "811c8c9be6300a60d0b75dbdad456f5b",
"assets/packages/flag/res/4x3/kz.svg": "59e6b4a55c87f8428dec75b5aeb4a0e5",
"assets/packages/flag/res/4x3/la.svg": "7652fbee2ee7b50bf1d47eafaac711c2",
"assets/packages/flag/res/4x3/lb.svg": "fb13548c077ef12d50fdd5986acde408",
"assets/packages/flag/res/4x3/lc.svg": "0c19b941034baed8abc93e724c96e7aa",
"assets/packages/flag/res/4x3/li.svg": "e2479aaa135a4c808a1ffff7eb9568ba",
"assets/packages/flag/res/4x3/lk.svg": "d3fe84dba663372b08e47aa77fea73b1",
"assets/packages/flag/res/4x3/lr.svg": "9f4950233c81fe87232d23d957f2ee39",
"assets/packages/flag/res/4x3/ls.svg": "d86ac2ce7ff0795525396d40a297541c",
"assets/packages/flag/res/4x3/lt.svg": "1ee1102e9b089c834f49c20149059139",
"assets/packages/flag/res/4x3/lu.svg": "1de1678264959ecfa469415b878a6c41",
"assets/packages/flag/res/4x3/lv.svg": "a0eae2fbebfbd426b2fd3ea147d4f1e0",
"assets/packages/flag/res/4x3/ly.svg": "c566de41004672b62f3bad7f7fc596ca",
"assets/packages/flag/res/4x3/ma.svg": "ec279c4f5abbf035dcb5d41adfe3e2ec",
"assets/packages/flag/res/4x3/mc.svg": "a6178ad659e292456ae4977b4f0bcdce",
"assets/packages/flag/res/4x3/md.svg": "bac4a4cb03ebf3e3b6e8964154a5cee6",
"assets/packages/flag/res/4x3/me.svg": "c6c9d038085091ee7c7b211ddcd9efc4",
"assets/packages/flag/res/4x3/mf.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/mg.svg": "57e0e21b021ac054cefda690bda50c3e",
"assets/packages/flag/res/4x3/mh.svg": "0138793ce7ae1112f7eea97f7f94b241",
"assets/packages/flag/res/4x3/mk.svg": "33a312a253037b55b2881ae9aec5c580",
"assets/packages/flag/res/4x3/ml.svg": "793acfabcb3dfdfd0dc1df81c4b08c15",
"assets/packages/flag/res/4x3/mm.svg": "b908ecfffe8de7b7f8b8786f7cf503e5",
"assets/packages/flag/res/4x3/mn.svg": "4e3c78b0965dbf31ef6630b318b97fa1",
"assets/packages/flag/res/4x3/mo.svg": "4e46ab981f577a19b9e408cbd6633d29",
"assets/packages/flag/res/4x3/mp.svg": "efd9bf42e429a6c29c93cd2b297ffc80",
"assets/packages/flag/res/4x3/mq.svg": "b3f6d471c9eef4e50b9dd9f3327cd2d2",
"assets/packages/flag/res/4x3/mr.svg": "581e18f70fb38dde43947c9c4cadbb1e",
"assets/packages/flag/res/4x3/ms.svg": "9724228ba817961c60d049ba988981eb",
"assets/packages/flag/res/4x3/mt.svg": "b889a21af2d2ecd11c97c3b3204cd300",
"assets/packages/flag/res/4x3/mu.svg": "623645d1b2d6e845d980584d24b3ea13",
"assets/packages/flag/res/4x3/mv.svg": "9ac00149799c5bfd8cd0b8f90154b1a9",
"assets/packages/flag/res/4x3/mw.svg": "cb11ad1bfca13a28ca44dc7d72d5fbf0",
"assets/packages/flag/res/4x3/mx.svg": "3ef2cb3848834795fa437aa02bd33065",
"assets/packages/flag/res/4x3/my.svg": "d389ba8dc439ca614c67afcbcc5e868b",
"assets/packages/flag/res/4x3/mz.svg": "288f1a58ce55d80438b22a3cfec9f960",
"assets/packages/flag/res/4x3/na.svg": "329023bac4d07bb52ba2929dba4d2f17",
"assets/packages/flag/res/4x3/nc.svg": "6ecb55a92bdb41022b049454133f8df0",
"assets/packages/flag/res/4x3/ne.svg": "df8df5bafe0cff382f6f0a41c2fdd0f4",
"assets/packages/flag/res/4x3/nf.svg": "372906d84fafadaa99f8d1d6ed7dc2d8",
"assets/packages/flag/res/4x3/ng.svg": "abcfd58172eadbbef75377270f896a49",
"assets/packages/flag/res/4x3/ni.svg": "7f57fc5cf25ea9aa4a45e717593d79d2",
"assets/packages/flag/res/4x3/nl.svg": "caec7df85906eb333b4beef973037364",
"assets/packages/flag/res/4x3/no.svg": "1a13241ff36624806ea8df3264caa66e",
"assets/packages/flag/res/4x3/np.svg": "686f09ff1b8d4ed1b709bbe933155963",
"assets/packages/flag/res/4x3/nr.svg": "e9e393f1bdfec998ea2e476662f3d8fd",
"assets/packages/flag/res/4x3/nu.svg": "a3a97b2892bb67b7a1a675c705565fa4",
"assets/packages/flag/res/4x3/nz.svg": "4ad30eca53d39e4837fd3537d3f467b2",
"assets/packages/flag/res/4x3/om.svg": "04f38e46829e78c43406fce6e91025da",
"assets/packages/flag/res/4x3/pa.svg": "e0126260692a0e41c490cf7e4f494e26",
"assets/packages/flag/res/4x3/pe.svg": "04cfacdc55894fab32a088079e28583b",
"assets/packages/flag/res/4x3/pf.svg": "1a74ae49513f0704eec7729a21cef2f4",
"assets/packages/flag/res/4x3/pg.svg": "3a524e8eb54e1567fbcf671bf64b987e",
"assets/packages/flag/res/4x3/ph.svg": "3f88b67fb7bdf1afefb4c49a7560c9c9",
"assets/packages/flag/res/4x3/pk.svg": "f4329b2101e993815df9fbb9e8ffa20b",
"assets/packages/flag/res/4x3/pl.svg": "5dd7c2afb3f4d2ca36b1dfb06fb2b2c9",
"assets/packages/flag/res/4x3/pm.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/pn.svg": "08a2fca66e96d8a3e534d08ec0099094",
"assets/packages/flag/res/4x3/pr.svg": "9dfe18692507e0062e1f305175d895b3",
"assets/packages/flag/res/4x3/ps.svg": "d8b7a2f4b805a28c6b9c4f1bad563d2b",
"assets/packages/flag/res/4x3/pt.svg": "62e1a2f71624ba6c8d2ff4670e04ccd2",
"assets/packages/flag/res/4x3/pw.svg": "c1a9b573e8a0336d60aaadf8695001e3",
"assets/packages/flag/res/4x3/py.svg": "344b8da743830281f822f431da921d48",
"assets/packages/flag/res/4x3/qa.svg": "352d05d180fae66d14ab5b2a6890ee24",
"assets/packages/flag/res/4x3/re.svg": "b3f6d471c9eef4e50b9dd9f3327cd2d2",
"assets/packages/flag/res/4x3/ro.svg": "e6bbef03e0d2b8d75d3ced9d49c6d0a7",
"assets/packages/flag/res/4x3/rs.svg": "9af916d1078c9dcc8df1945492f41b43",
"assets/packages/flag/res/4x3/ru.svg": "21f9a6907d38ce89719efd96308dc9d3",
"assets/packages/flag/res/4x3/rw.svg": "39f0bb91270dfb43c0af9091881675de",
"assets/packages/flag/res/4x3/sa.svg": "fbe1d8807b42998ef24ebf7e37e158c5",
"assets/packages/flag/res/4x3/sb.svg": "d9dbbd81c90c341314b76af96244ddf0",
"assets/packages/flag/res/4x3/sc.svg": "1dff3f9971c5679cc64e469b509949de",
"assets/packages/flag/res/4x3/sd.svg": "8263a8aa3f5bae533ab79eb323e0600e",
"assets/packages/flag/res/4x3/se.svg": "dbb3712f086d8762c69a393e74a9dd16",
"assets/packages/flag/res/4x3/sg.svg": "07fa84479047538e742ef10937a92467",
"assets/packages/flag/res/4x3/sh.svg": "efa815b4037fd93505f4a11f337da614",
"assets/packages/flag/res/4x3/si.svg": "5c964cce4af5452297c5030f12ca67f4",
"assets/packages/flag/res/4x3/sj.svg": "c8c51920fb31b60e13bee954aaf29242",
"assets/packages/flag/res/4x3/sk.svg": "4a3ca863346baadc0a14160492a9fd73",
"assets/packages/flag/res/4x3/sl.svg": "a349044894da800e10d5304d168533ed",
"assets/packages/flag/res/4x3/sm.svg": "0c84deb06f419aff4ab409a5bf34d06d",
"assets/packages/flag/res/4x3/sn.svg": "9277b4b37bf824e9c9dcab8d2dbacf0d",
"assets/packages/flag/res/4x3/so.svg": "c7d844657f06978ae5994df529dafcde",
"assets/packages/flag/res/4x3/sr.svg": "b00a612437122c56b8878d61f225c274",
"assets/packages/flag/res/4x3/ss.svg": "5079b6ea20253fa65f83b9f7b765c7ce",
"assets/packages/flag/res/4x3/st.svg": "6f12508b8b857a8a7e97b21dee8aea0a",
"assets/packages/flag/res/4x3/sv.svg": "e41390a6da86c77b9ea1487c4ed4423c",
"assets/packages/flag/res/4x3/sx.svg": "5193cc8de3bc93f2ca91290120a3dc4f",
"assets/packages/flag/res/4x3/sy.svg": "10745021b09117d027ed56602a8b0a49",
"assets/packages/flag/res/4x3/sz.svg": "3059c8fc502590e795fdea5f99c82c75",
"assets/packages/flag/res/4x3/ta.svg": "efa815b4037fd93505f4a11f337da614",
"assets/packages/flag/res/4x3/tc.svg": "f220a2825e3b044fbfdb91391c21b439",
"assets/packages/flag/res/4x3/td.svg": "b36fa5c6f8a17953ebc4cee7630fb321",
"assets/packages/flag/res/4x3/tf.svg": "48e7c33d2d147e16ad7c799edde9fb5c",
"assets/packages/flag/res/4x3/tg.svg": "50da95e9807ceb538949c07c691d0553",
"assets/packages/flag/res/4x3/th.svg": "88f2c5155a03112304cc158fc81b0b03",
"assets/packages/flag/res/4x3/tj.svg": "47bce679fc09ee66263657d8c2fa27d2",
"assets/packages/flag/res/4x3/tk.svg": "27218fa60046ed670fabfeff5b51036b",
"assets/packages/flag/res/4x3/tl.svg": "480fe4ce5510fc92669711eddedbc44e",
"assets/packages/flag/res/4x3/tm.svg": "7b91f1bcf7f077ba6b93a5cf9b97e8c2",
"assets/packages/flag/res/4x3/tn.svg": "2d05d1319948647925425ec55dfe127c",
"assets/packages/flag/res/4x3/to.svg": "d5db3c26472b5f4ff39381a6a9fc63a7",
"assets/packages/flag/res/4x3/tr.svg": "83d5d798f4db626a63f97cba2f49d7f1",
"assets/packages/flag/res/4x3/tt.svg": "a7a67a1c3918f5861529bb3232c1e9f3",
"assets/packages/flag/res/4x3/tv.svg": "b4d35c64512840c831d881cabbb2243f",
"assets/packages/flag/res/4x3/tw.svg": "ba4b1f4fea46c44bbafa203b7253a5a4",
"assets/packages/flag/res/4x3/tz.svg": "c15872f9db5f691102a68b0ca0f403e8",
"assets/packages/flag/res/4x3/ua.svg": "881630a88ad5080898f327ddc46a563b",
"assets/packages/flag/res/4x3/ug.svg": "b9b4105dc228b664ac503304eee81c44",
"assets/packages/flag/res/4x3/um.svg": "0445fdefbe06eee8ff66251a14fe8e1b",
"assets/packages/flag/res/4x3/un.svg": "ba64785d5d5112fa70c26391a0d35753",
"assets/packages/flag/res/4x3/us.svg": "08bb56b85364ea919c180e0a97c99377",
"assets/packages/flag/res/4x3/uy.svg": "3509969b09793d0fee29f9ca39c9162e",
"assets/packages/flag/res/4x3/uz.svg": "74412758a88436064535a7ada52ad609",
"assets/packages/flag/res/4x3/va.svg": "7251c0ceae1ae0224b5f93ebb22c472f",
"assets/packages/flag/res/4x3/vc.svg": "72a865d8bd142b60e36c5752a809e972",
"assets/packages/flag/res/4x3/ve.svg": "8be16110a2e5201ec62073ab5f0adb33",
"assets/packages/flag/res/4x3/vg.svg": "156116e90998fafd103f91b9201ec02d",
"assets/packages/flag/res/4x3/vi.svg": "bd90436bc9e50c36f1f3510df2a37e2b",
"assets/packages/flag/res/4x3/vn.svg": "b0132c8c01f27469fcfd91f3663e8a93",
"assets/packages/flag/res/4x3/vu.svg": "295a4a49518c8ce16e55ef227ae75fc8",
"assets/packages/flag/res/4x3/wf.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/ws.svg": "540f67ffb726b28eed7a42f9c22b95ad",
"assets/packages/flag/res/4x3/xk.svg": "e6b54955df68b1b1f7de72a489df4c16",
"assets/packages/flag/res/4x3/xx.svg": "d9dcde3391d83c5595a13f7c30850fc8",
"assets/packages/flag/res/4x3/ye.svg": "b469b8d7233013519198a92efb3b07b2",
"assets/packages/flag/res/4x3/yt.svg": "492398b7ff8c7deba44235dcb7e9e569",
"assets/packages/flag/res/4x3/za.svg": "56d19ba3e1393ca56b4b264b53b3370b",
"assets/packages/flag/res/4x3/zm.svg": "b7fc4c01f663a8254202cf3ca28bdfdf",
"assets/packages/flag/res/4x3/zw.svg": "dc58fbdd9dcfe5677b10b85935c9840c",
"assets/packages/font_awesome_flutter/lib/fonts/Font-Awesome-7-Brands-Regular-400.otf": "d40c67ce9f52d4bf087e61453006393c",
"assets/packages/font_awesome_flutter/lib/fonts/Font-Awesome-7-Free-Regular-400.otf": "02bc402a35fb3a5b8156e52265cb894f",
"assets/packages/font_awesome_flutter/lib/fonts/Font-Awesome-7-Free-Solid-900.otf": "27c6dca5279f7d3357363e01dc75eb5f",
"assets/shaders/ink_sparkle.frag": "ecc85a2e95f5e9f53123dcaf8cb9b6ce",
"assets/shaders/stretch_effect.frag": "40d68efbbf360632f614c731219e95f0",
"canvaskit/canvaskit.js": "8331fe38e66b3a898c4f37648aaf7ee2",
"canvaskit/canvaskit.js.symbols": "a3c9f77715b642d0437d9c275caba91e",
"canvaskit/canvaskit.wasm": "9b6a7830bf26959b200594729d73538e",
"canvaskit/chromium/canvaskit.js": "a80c765aaa8af8645c9fb1aae53f9abf",
"canvaskit/chromium/canvaskit.js.symbols": "e2d09f0e434bc118bf67dae526737d07",
"canvaskit/chromium/canvaskit.wasm": "a726e3f75a84fcdf495a15817c63a35d",
"canvaskit/skwasm.js": "8060d46e9a4901ca9991edd3a26be4f0",
"canvaskit/skwasm.js.symbols": "3a4aadf4e8141f284bd524976b1d6bdc",
"canvaskit/skwasm.wasm": "7e5f3afdd3b0747a1fd4517cea239898",
"canvaskit/skwasm_heavy.js": "740d43a6b8240ef9e23eed8c48840da4",
"canvaskit/skwasm_heavy.js.symbols": "0755b4fb399918388d71b59ad390b055",
"canvaskit/skwasm_heavy.wasm": "b0be7910760d205ea4e011458df6ee01",
"favicon.png": "5dcef449791fa27946b3d35ad8803796",
"flutter.js": "24bc71911b75b5f8135c949e27a2984e",
"flutter_bootstrap.js": "1c8841c58d7b7428a6c7d0b7eb090dc9",
"icons/Icon-192.png": "ac9a721a12bbc803b44f645561ecb1e1",
"icons/Icon-512.png": "96e752610906ba2a93c65f8abe1645f1",
"icons/Icon-maskable-192.png": "c457ef57daa1d16f64b27b786ec2ea3c",
"icons/Icon-maskable-512.png": "301a7604d45b3e739efc881eb04896ea",
"index.html": "bb63c9450dd0dee715019380dd8b1580",
"/": "bb63c9450dd0dee715019380dd8b1580",
"main.dart.js": "7aa73281da1643ae0c5751bb212b38b5",
"manifest.json": "d2bd03d6a8d5372309345ac916ffe736",
"version.json": "4f2a7adf7c247595f42246a4d92c90ad"};
// The application shell files that are downloaded before a service worker can
// start.
const CORE = ["main.dart.js",
"index.html",
"flutter_bootstrap.js",
"assets/AssetManifest.bin.json",
"assets/FontManifest.json"];

// During install, the TEMP cache is populated with the application shell files.
self.addEventListener("install", (event) => {
  self.skipWaiting();
  return event.waitUntil(
    caches.open(TEMP).then((cache) => {
      return cache.addAll(
        CORE.map((value) => new Request(value, {'cache': 'reload'})));
    })
  );
});
// During activate, the cache is populated with the temp files downloaded in
// install. If this service worker is upgrading from one with a saved
// MANIFEST, then use this to retain unchanged resource files.
self.addEventListener("activate", function(event) {
  return event.waitUntil(async function() {
    try {
      var contentCache = await caches.open(CACHE_NAME);
      var tempCache = await caches.open(TEMP);
      var manifestCache = await caches.open(MANIFEST);
      var manifest = await manifestCache.match('manifest');
      // When there is no prior manifest, clear the entire cache.
      if (!manifest) {
        await caches.delete(CACHE_NAME);
        contentCache = await caches.open(CACHE_NAME);
        for (var request of await tempCache.keys()) {
          var response = await tempCache.match(request);
          await contentCache.put(request, response);
        }
        await caches.delete(TEMP);
        // Save the manifest to make future upgrades efficient.
        await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
        // Claim client to enable caching on first launch
        self.clients.claim();
        return;
      }
      var oldManifest = await manifest.json();
      var origin = self.location.origin;
      for (var request of await contentCache.keys()) {
        var key = request.url.substring(origin.length + 1);
        if (key == "") {
          key = "/";
        }
        // If a resource from the old manifest is not in the new cache, or if
        // the MD5 sum has changed, delete it. Otherwise the resource is left
        // in the cache and can be reused by the new service worker.
        if (!RESOURCES[key] || RESOURCES[key] != oldManifest[key]) {
          await contentCache.delete(request);
        }
      }
      // Populate the cache with the app shell TEMP files, potentially overwriting
      // cache files preserved above.
      for (var request of await tempCache.keys()) {
        var response = await tempCache.match(request);
        await contentCache.put(request, response);
      }
      await caches.delete(TEMP);
      // Save the manifest to make future upgrades efficient.
      await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
      // Claim client to enable caching on first launch
      self.clients.claim();
      return;
    } catch (err) {
      // On an unhandled exception the state of the cache cannot be guaranteed.
      console.error('Failed to upgrade service worker: ' + err);
      await caches.delete(CACHE_NAME);
      await caches.delete(TEMP);
      await caches.delete(MANIFEST);
    }
  }());
});
// The fetch handler redirects requests for RESOURCE files to the service
// worker cache.
self.addEventListener("fetch", (event) => {
  if (event.request.method !== 'GET') {
    return;
  }
  var origin = self.location.origin;
  var key = event.request.url.substring(origin.length + 1);
  // Redirect URLs to the index.html
  if (key.indexOf('?v=') != -1) {
    key = key.split('?v=')[0];
  }
  if (event.request.url == origin || event.request.url.startsWith(origin + '/#') || key == '') {
    key = '/';
  }
  // If the URL is not the RESOURCE list then return to signal that the
  // browser should take over.
  if (!RESOURCES[key]) {
    return;
  }
  // If the URL is the index.html, perform an online-first request.
  if (key == '/') {
    return onlineFirst(event);
  }
  event.respondWith(caches.open(CACHE_NAME)
    .then((cache) =>  {
      return cache.match(event.request).then((response) => {
        // Either respond with the cached resource, or perform a fetch and
        // lazily populate the cache only if the resource was successfully fetched.
        return response || fetch(event.request).then((response) => {
          if (response && Boolean(response.ok)) {
            cache.put(event.request, response.clone());
          }
          return response;
        });
      })
    })
  );
});
self.addEventListener('message', (event) => {
  // SkipWaiting can be used to immediately activate a waiting service worker.
  // This will also require a page refresh triggered by the main worker.
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
    return;
  }
  if (event.data === 'downloadOffline') {
    downloadOffline();
    return;
  }
});
// Download offline will check the RESOURCES for all files not in the cache
// and populate them.
async function downloadOffline() {
  var resources = [];
  var contentCache = await caches.open(CACHE_NAME);
  var currentContent = {};
  for (var request of await contentCache.keys()) {
    var key = request.url.substring(origin.length + 1);
    if (key == "") {
      key = "/";
    }
    currentContent[key] = true;
  }
  for (var resourceKey of Object.keys(RESOURCES)) {
    if (!currentContent[resourceKey]) {
      resources.push(resourceKey);
    }
  }
  return contentCache.addAll(resources);
}
// Attempt to download the resource online before falling back to
// the offline cache.
function onlineFirst(event) {
  return event.respondWith(
    fetch(event.request).then((response) => {
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch((error) => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.match(event.request).then((response) => {
          if (response != null) {
            return response;
          }
          throw error;
        });
      });
    })
  );
}
