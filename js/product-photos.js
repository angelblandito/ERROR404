/*
 * Fotos reales de los productos (Unsplash, licencia gratuita de uso comercial).
 * Cada producto se clasifica por su nombre y recibe una foto del tipo que le
 * corresponde. Ninguna foto está en dos tipos. Al cargar el catálogo,
 * assignPhotos() reparte las fotos de cada tipo en orden de id_product: cada
 * producto recibe una foto distinta mientras el tipo tenga fotos suficientes.
 * El orden en que se usan las fotos está en illustrations.js.
 */

const PHOTO_URL = 'https://images.unsplash.com/photo-';
const PHOTO_SIZE = '?auto=format&fit=crop&w=600&h=600&q=70';

const PHOTOS = {
    running: ['1542291026-7eec264c27ff', '1606107557195-0e29a4b5b4aa', '1571008887538-b36bb32f4571',
        '1491553895911-0055eca6402d', '1600185365483-26d7a4cc7519', '1600185365926-3a2ce3cdb9eb',
        '1560769629-975ec94e6a86', '1597892657493-6847b9640bac', '1608667508764-33cf0726b13a',
        '1562183241-b937e95585b6', '1551107696-a4b0c5a0d9a2', '1539185441755-769473a23570',
        '1792955104796-31d15ec8c28f', '1581888748626-2a3f240a6f9f', '1582898967731-b5834427fd66',
        '1793415486840-34ffd80b1dd3', '1786181321837-4fe85242d17c'],
    street: ['1595950653106-6c9ebd614d3a', '1525966222134-fcfa99b8ae77', '1761777939781-2fb1b636d5fc',
        '1600269452121-4f2416e55c28', '1771049873881-45b23a2e9847', '1549298916-b41d501d3772',
        '1543508282-6319a3e2621f', '1608231387042-66d1773070a5', '1759306188476-99169b6259e3',
        '1676379760823-ebf91f45de0b', '1562105962-2fbaaf107fe3', '1700673698394-270378d1a70d',
        '1784715435231-5547da837fa9', '1784236404723-bc958ead7dd5', '1783139965231-9ecfc25eaf45',
        '1601997354917-c33e09c71fa3', '1561909848-977d0617f275'],
    courtShoe: ['1612387048732-1840c48c0976', '1552346154-21d32810aba3', '1605523741177-cd660595c2cf',
        '1595909336425-5bf541155dec', '1512374382149-233c42b6a83b', '1533681018184-68bd1d883b97'],
    cleats: ['1511886929837-354d827aae26', '1768696082282-df2812a4f7d8', '1768696082264-44f14594ca2c',
        '1600679472829-3044539ce8ed', '1511426463457-0571e247d816', '1768696082140-e042cf8223e7'],
    slides: ['1603487742131-4160ec999306', '1622920799137-86c891159e44', '1529447105779-c529acf6d60b',
        '1764268641240-2675102b111d'],
    flipflops: ['1601946771183-2e0659c5ae69', '1617370653117-ff3a94af6cef', '1659963970293-b12cfeb286c5'],
    boots: ['1768155376869-c07db06f2c47', '1768699143835-15217d5d5a57', '1542336945-a4b0d29304c5'],
    tee: ['1581655353564-df123a1eb820', '1583743814966-8936f5b7be1a', '1521572163474-6864f9cf17ab',
        '1618354691373-d851c5c3a990', '1622445275463-afa2ab738c34', '1576566588028-4147f3842f27',
        '1778671394516-8270eac13c42', '1759572095384-1a7e646d0d4f', '1651761179569-4ba2aa054997',
        '1618354691438-25bc04584c23', '1618677603286-0ec56cb6e1b5', '1620799140408-edc6dcb6d633',
        '1618354691551-44de113f0164', '1564584217132-2271feaeb3c5'],
    jersey: ['1597630907602-356d09900200', '1630552227424-81c9e52d7c83', '1637666532931-b835a227b955',
        '1577212017184-80cc0da11082', '1552066379-e7bfd22155c5', '1580089595767-98745d7025c5',
        '1698322830976-4a3fdfb293d5', '1738091063217-1f59463342c9'],
    top: ['1606902965551-dce093cda6e7', '1584863495140-a320b13a11a8', '1706029831374-e236f69cbe8b',
        '1531520563951-4c0e3d3fcacc', '1682523426986-92746735ccc2', '1594730036615-a0f77497536f',
        '1759476530926-f80b01cb6c27', '1759476530591-e6d197cc70f7'],
    tank: ['1593419528756-3cdfa1615b86', '1600696491085-19dbbeb4f8c1', '1601838165307-6a5a08616f97',
        '1724490056260-44bf1de2617e', '1785355146214-271c6b6eba58', '1599447292411-637efa33e046',
        '1609899494145-417d7327ea9e'],
    leggings: ['1618355281951-a174b87198e2', '1626026397008-3316047db4fc', '1650461970708-7bf32499516d',
        '1606903037631-f09fd0bd74b4', '1641808887823-b3201916a57d', '1721969982799-fb9a78c43e7f',
        '1641808887202-3fea3721dc7f', '1645318800735-737d3a422de6', '1597586785094-c9c247ec343f',
        '1623171875321-db8b93788a44', '1645810798586-08e892108d67', '1770026137084-bc03cad73eaf',
        '1779153617416-8d4729084fcc', '1768853990312-0e20e64cf87f', '1784924176793-7fe9f8cd96e7',
        '1597299001669-6454fec8ac25', '1612310480700-e64c5c42dae7', '1523654999808-59842135e652',
        '1763771522867-c26bf75f12bc', '1762331650558-4b345e9080ad', '1768929035644-6e146b35acd8',
        '1579454563399-9da67b065830', '1599751449353-779b810ebb37', '1760594386933-08dfdef455de',
        '1541694458248-5aa2101c77df'],
    shorts: ['1635929171657-ccb333d714f5', '1715532098304-1e81e1f42600', '1785355064366-68a094d13651',
        '1600878585887-c2b9530999a1', '1640943136566-3edeb13e3d3b', '1762744826900-ff6cdf807d79',
        '1554139844-af2fc8ad3a3a', '1695918425801-41dd27ed8277', '1602190420103-683df5093e86',
        '1614660762109-bb0157fcbd7d', '1588689653274-cd16f09dc67b', '1594768928363-26cbe4cc86af'],
    courtShorts: ['1787755939978-bc3134c8590a', '1593476123435-4dbefa591fca', '1763740355836-a26addadfbbd'],
    pants: ['1594633312681-425c7b97ccd1', '1601561446301-fecc99036f4b', '1580906853203-f493cea9ff28',
        '1715532098035-a343b26eaeaa', '1552902875-9ac1f9fe0c07', '1580906853305-5702e648164e',
        '1607962837359-5e7e89f86776', '1588117305388-c2631a279f82', '1588117260148-b47818741c74',
        '1779676086744-a83b1f0e00bd', '1590330297626-d7aff25a0431', '1789110520164-2389992e5d0f',
        '1789110853665-11a03e391f04', '1602573991155-21f0143bb45c'],
    hoodie: ['1556821840-3a63f95609a7', '1579572331145-5e53b299c64e', '1601063476271-a159c71ab0b3',
        '1620799140188-3b2a02fd9a77', '1680292783974-a9a336c10366', '1609873814058-a8928924184a',
        '1757495404191-e94ed7e70046', '1564557287817-3785e38ec1f5', '1685354218016-3899c9ef79ad',
        '1578768079052-aa76e52ff62e', '1685354217981-26c14a211bf8', '1761792579442-bd9eb607c62f',
        '1615397587950-3cbb55f95b77', '1513789181297-6f2ec112c0bc', '1611817757591-c3f345024273',
        '1578587018452-892bacefd3f2', '1499971856191-1a420a42b498', '1774128089578-62b9454c0697',
        '1781935108662-9299bfb751bb'],
    jacket: ['1624548140129-74786c5f1279', '1643622782660-30dedcd8d75a', '1788463127138-01e87414a936',
        '1571867424485-369464ed33cc', '1578964046312-69d266a3e739', '1611308725032-74f0a551d018',
        '1567955465154-078c60ff5c9e', '1768983953826-231e8ef0b6dc', '1586360727847-108cbb274ad1',
        '1567955465163-c355df3b74bf', '1774413768733-c5a9c9c90510', '1654719796836-62b889d4598d'],
    puffer: ['1706765779494-2705542ebe74', '1776466532037-3e23b94dc1f1', '1769689387719-f11faac08cbe',
        '1548624313-0396c75e4b1a', '1611025504703-8c143abe6996', '1608113562252-b320e7628e17'],
    cap: ['1588850561407-ed78c282e89b', '1521369909029-2afed882baee', '1691256676359-20e5c6d4bc92',
        '1645266729222-17cd32e06fd0', '1560774358-d727658f457c', '1785336472737-78291368800d',
        '1777455163879-92852416eb36', '1720534490358-bc2ad29d51d5', '1759012036404-975090ca1f9a',
        '1466992133056-ae8de8e22809'],
    beanie: ['1636378169530-151266360413', '1618354691792-d1d42acfd860', '1607265705067-ab09cf1f18d0',
        '1630691650107-53dd500d2907'],
    backpack: ['1553062407-98eeb64c6a62', '1581605405669-fcdf81165afa', '1622560480654-d96214fdc887',
        '1765516833058-c322c800fee1', '1622560480605-d83c853bc5c3', '1680039211156-66c721b87625',
        '1779896412307-d1aeffd1203f', '1526736068954-3d647ef94973', '1577733975197-3b950ca5cabe',
        '1708622833152-924c6e364138', '1448582649076-3981753123b5'],
    hikePack: ['1509762774605-f07235a08f1f', '1622260614153-03223fb72052', '1789126344009-8e7dd743202c',
        '1720911478715-8bef445c2522'],
    bottle: ['1625708458528-802ec79b1ed8', '1544003484-3cd181d17917', '1530711654140-23ef9ad45094',
        '1523362628745-0c100150b504', '1568395216634-ab1b1e848751'],
    socks: ['1585499583264-491df5142e83', '1640026199235-c24aa417b552', '1564379976409-79bd0786fff1',
        '1640025867572-f6b3a8410c81', '1778852532680-19be841dd3fd'],
    sunglasses: ['1572635196237-14b3f281503f', '1584036553516-bf83210aa16c', '1577803645773-f96470509666',
        '1511499767150-a48a237f0083', '1785672021160-29cccd65500d', '1764783103855-2b747b9c8417',
        '1610136649349-0f646f318053', '1708799366365-a24608103bed', '1708799366362-f3533dd266c2',
        '1718967807877-f2e04ffc7343', '1653038282189-803202722a05', '1758552322632-ba288778c770',
        '1473496169904-658ba7c44d8a', '1608539733292-190446b22b83', '1761864293845-90f7ff41739b',
        '1611222777277-61319d63ca94', '1508296695146-257a814070b4', '1566421966482-ad8076104d8e',
        '1782840838231-bda19ccbe919', '1760042653637-9a845becfc82'],
    goggles: ['1607958674115-05b24858a945', '1781123542514-9b2e234c4ad3', '1467044705596-744699fa8931',
        '1555836685-31706a63b6f4', '1786132371240-1034241806b2', '1533060629428-48484ce98a74',
        '1747494749148-c3f5018799c2', '1747494749385-c75103afc37d'],
    skiGoggles: ['1768245427630-664cd80ded29', '1768375210833-5908e9d275eb'],
    goalie: ['1632072820781-79f3a064f640', '1760177379284-b68471fdd217', '1642850470069-769d2dcc85f8',
        '1793976233538-064833335040', '1793976256836-6aad829b04fc', '1774201427012-fd1eb39deddb',
        '1780882289143-e7dd135c1e99', '1766525087638-fcc969c61b37'],
    boxing: ['1549719386-74dfcbf7dbed', '1669500635486-53b02ec7919b', '1521800641212-77d98bb90d21',
        '1779369958758-97376feb5aaa', '1780840156934-3ccb8dc05aa8', '1766264183468-c163676c5154',
        '1788445701100-f78832ced90a', '1633394782368-6e7260566004', '1561532325-7d5231a2dede',
        '1716307043003-dbe6a5cc496e', '1660212074310-6d7ed176c746'],
    gymGloves: ['1557127972-1c446ea89ea5', '1570442387127-66eb80e00938', '1522956957021-9f2437e01594',
        '1574269909183-f862ca9ae22d', '1636124883069-52aef60095f1', '1789718759468-74522c0083c0',
        '1793990824534-feca0085890c', '1566905836650-f0f0aaba045c'],
    soccerBall: ['1579952363873-27f3bade9f55', '1575361204480-aadea25e6e68', '1516567727245-ad8c68f3ec93',
        '1764040561192-dc860c4ecf8a', '1769356636430-e04718f78ee8', '1660926655155-8b1f8f9079f5',
        '1587329310686-91414b8e3cb7', '1486286701208-1d58e9338013', '1570498839593-e565b39455fc',
        '1614632537190-23e4146777db', '1543351611-58f69d7c1781'],
    basketball: ['1608508136365-89375aa78e0c', '1595861021888-e8192a7f774e', '1484482340112-e1e2682b4856',
        '1567113379515-6e85e7168eb1', '1627627256672-027a4613d028', '1528310782510-2f00164b609f'],
    volleyball: ['1666901356149-93f2eb3ba5a2', '1588492069485-d05b56b2831d', '1645398709708-eca899c71a11',
        '1565818819174-d76ccec8b2af', '1567880479532-981be7cd7763'],
    beachVolley: ['1592656094267-764a45160876', '1593115379577-a21ea97d6645', '1754409537748-185935ae5e2e'],
    americanFootball: ['1566577739254-faaafa7b4fa7', '1566579090262-51cde5ebe92e', '1566577739112-5180d4bf9390',
        '1566577738928-c49fe1932e84', '1782007573770-e3643370bb0e', '1719518701287-72bb9b3366ee',
        '1485313260896-6e6edf486858'],
    padelBalls: ['1759819599208-0b79a7f3f347', '1541744573515-478c959628a0', '1785703531263-f43b0bb3fcf8',
        '1558365849-6ebd8b0454b2', '1714508969012-7ac9c063b39a', '1580153111806-5007b971dfe7'],
    racket: ['1657704358775-ed705c7388d2', '1787768647981-dab3d52f8f04', '1612534847738-b3af9bc31f0c',
        '1658723826297-fe4d1b1e6600', '1646649853703-7645147474ba'],
    dumbbells: ['1638536532686-d610adfc8e5c', '1576678927484-cc907957088c', '1672344048213-76b6e77304bd',
        '1770493895453-4f758c40d11d', '1775993699105-4d18bcac7e54']
};

/* Tipo de tenis: solo se revisa cuando el nombre empieza con "tenis". */
const SHOE_KEYWORDS = [
    ['de futbol', 'cleats'], ['basketball', 'courtShoe'], ['court', 'courtShoe'],
    ['urbano', 'street'], ['street', 'street'], ['retro', 'street'], ['escolar', 'street'],
    ['sprint jr', 'street'], ['gift', 'street'], ['canvas', 'street'], ['holiday', 'street']
];

/* Palabras del nombre (sin acentos, en minúsculas). Gana la primera que aparezca. */
const KEYWORDS = [
    ['falda', 'shorts'], ['balon de futbol americano', 'americanFootball'],
    ['balon de basquetbol', 'basketball'], ['voleibol de playa', 'beachVolley'], ['voleibol', 'volleyball'],
    ['balon', 'soccerBall'], ['pelotas', 'padelBalls'], ['raqueta', 'racket'],
    ['guantes de box', 'boxing'], ['guantes de portero', 'goalie'], ['guantes de gimnasio', 'gymGloves'],
    ['lentes de esqui', 'skiGoggles'], ['goggles', 'goggles'], ['lentes', 'sunglasses'], ['mancuerna', 'dumbbells'], ['botella', 'bottle'],
    ['calceta', 'socks'], ['mochila de hidratacion', 'hikePack'], ['mochila', 'backpack'],
    ['gorro', 'beanie'], ['gorra', 'cap'], ['botas', 'boots'],
    ['sandalias de playa', 'flipflops'], ['sandalia', 'slides'],
    ['tenis', 'running'], ['calzado', 'running'],
    ['jersey', 'jersey'], ['tank', 'tank'], ['top', 'top'], ['playera', 'tee'],
    ['short de basquetbol', 'courtShorts'], ['short', 'shorts'],
    ['legging', 'leggings'], ['pants', 'pants'], ['jogger', 'pants'],
    ['acolchada', 'puffer'], ['puffer', 'puffer'], ['alpine', 'puffer'],
    ['chamarra', 'jacket'], ['rompevientos', 'jacket'], ['sudadera', 'hoodie'], ['hoodie', 'hoodie']
];

const BY_CATEGORY = {
    tops: 'tee', bottoms: 'shorts', leggings: 'leggings', hoodies: 'hoodie', footwear: 'running',
    accessories: 'backpack', eyewear: 'sunglasses', gloves: 'gymGloves', balls: 'soccerBall'
};

function plain(text) {
    return String(text || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
}

function photoKind(product) {
    const name = plain(product.name);
    if (name.indexOf('tenis') === 0) {
        for (const [word, kind] of SHOE_KEYWORDS) {
            if (name.indexOf(word) >= 0) return kind;
        }
    }
    for (const [word, kind] of KEYWORDS) {
        if (name.indexOf(word) >= 0) return kind;
    }
    return BY_CATEGORY[product.category_code] || null;
}

/* Número estable a partir del nombre: para un producto que no estaba al repartir las fotos. */
function pick(list, name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    return list[hash % list.length];
}

const assigned = new Map();

/* El n-ésimo producto de cada tipo (por id_product) recibe la n-ésima foto del tipo. */
export function assignPhotos(products) {
    assigned.clear();
    const used = {};
    products.slice().sort(function (a, b) { return a.id_product - b.id_product; }).forEach(function (p) {
        const kind = photoKind(p);
        const list = kind ? PHOTOS[kind] : null;
        if (!list || !list.length) return;
        const n = used[kind] || 0;
        used[kind] = n + 1;
        assigned.set(p.id_product, list[n % list.length]);
    });
}

export function stockPhoto(product) {
    let id = assigned.get(product.id_product);
    if (!id) {
        const kind = photoKind(product);
        const list = kind ? PHOTOS[kind] : null;
        if (!list || !list.length) return null;
        id = pick(list, plain(product.name));
    }
    return PHOTO_URL + id + PHOTO_SIZE;
}
