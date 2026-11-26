/*
 * Fotos reales de los productos (Unsplash, licencia gratuita de uso comercial).
 * Cada producto se clasifica por su nombre y recibe una foto del tipo que le
 * corresponde. Si hay varias fotos para un tipo, se elige una según el nombre
 * del producto, así dos productos parecidos no muestran la misma imagen.
 * El orden en que se usan las fotos está en illustrations.js.
 */

const PHOTO_URL = 'https://images.unsplash.com/photo-';
const PHOTO_SIZE = '?auto=format&fit=crop&w=600&h=600&q=70';

const PHOTOS = {
    running: ['1542291026-7eec264c27ff', '1606107557195-0e29a4b5b4aa', '1571008887538-b36bb32f4571',
        '1491553895911-0055eca6402d', '1600185365483-26d7a4cc7519', '1600185365926-3a2ce3cdb9eb', '1560769629-975ec94e6a86', '1597892657493-6847b9640bac'],
    street: ['1595950653106-6c9ebd614d3a', '1525966222134-fcfa99b8ae77', '1761777939781-2fb1b636d5fc', '1600269452121-4f2416e55c28', '1771049873881-45b23a2e9847'],
    courtShoe: ['1612387048732-1840c48c0976'],
    cleats: ['1511886929837-354d827aae26', '1768696082282-df2812a4f7d8', '1768696082264-44f14594ca2c',
        '1600679472829-3044539ce8ed', '1511426463457-0571e247d816', '1768696082140-e042cf8223e7'],
    slides: ['1603487742131-4160ec999306', '1622920799137-86c891159e44'],
    flipflops: ['1601946771183-2e0659c5ae69', '1617370653117-ff3a94af6cef', '1659963970293-b12cfeb286c5'],
    boots: ['1768155376869-c07db06f2c47', '1768699143835-15217d5d5a57', '1542336945-a4b0d29304c5'],
    tee: ['1581655353564-df123a1eb820', '1583743814966-8936f5b7be1a', '1521572163474-6864f9cf17ab', '1618354691373-d851c5c3a990', '1622445275463-afa2ab738c34', '1576566588028-4147f3842f27'],
    jersey: ['1597630907602-356d09900200', '1630552227424-81c9e52d7c83', '1637666532931-b835a227b955'],
    top: ['1606902965551-dce093cda6e7', '1584863495140-a320b13a11a8', '1706029831374-e236f69cbe8b', '1531520563951-4c0e3d3fcacc'],
    tank: ['1593419528756-3cdfa1615b86', '1600696491085-19dbbeb4f8c1'],
    leggings: ['1618355281951-a174b87198e2', '1626026397008-3316047db4fc', '1531520563951-4c0e3d3fcacc', '1650461970708-7bf32499516d', '1584863495140-a320b13a11a8', '1606902965551-dce093cda6e7'],
    shorts: ['1635929171657-ccb333d714f5', '1715532098304-1e81e1f42600', '1785355064366-68a094d13651',
        '1600878585887-c2b9530999a1', '1640943136566-3edeb13e3d3b', '1762744826900-ff6cdf807d79'],
    courtShorts: ['1787755939978-bc3134c8590a'],
    pants: ['1593419528756-3cdfa1615b86', '1594633312681-425c7b97ccd1', '1601561446301-fecc99036f4b',
        '1580906853203-f493cea9ff28', '1715532098035-a343b26eaeaa', '1552902875-9ac1f9fe0c07'],
    hoodie: ['1556821840-3a63f95609a7', '1579572331145-5e53b299c64e', '1601063476271-a159c71ab0b3', '1620799140188-3b2a02fd9a77', '1680292783974-a9a336c10366', '1609873814058-a8928924184a'],
    jacket: ['1624548140129-74786c5f1279', '1643622782660-30dedcd8d75a', '1788463127138-01e87414a936'],
    puffer: ['1706765779494-2705542ebe74', '1776466532037-3e23b94dc1f1'],
    cap: ['1588850561407-ed78c282e89b', '1521369909029-2afed882baee', '1691256676359-20e5c6d4bc92', '1645266729222-17cd32e06fd0', '1560774358-d727658f457c', '1785336472737-78291368800d'],
    beanie: ['1636378169530-151266360413', '1618354691792-d1d42acfd860', '1607265705067-ab09cf1f18d0', '1630691650107-53dd500d2907'],
    backpack: ['1553062407-98eeb64c6a62', '1581605405669-fcdf81165afa', '1622560480654-d96214fdc887', '1765516833058-c322c800fee1'],
    hikePack: ['1509762774605-f07235a08f1f'],
    bottle: ['1625708458528-802ec79b1ed8', '1544003484-3cd181d17917', '1530711654140-23ef9ad45094', '1523362628745-0c100150b504', '1568395216634-ab1b1e848751'],
    socks: ['1585499583264-491df5142e83', '1640026199235-c24aa417b552', '1564379976409-79bd0786fff1', '1640025867572-f6b3a8410c81', '1778852532680-19be841dd3fd'],
    sunglasses: ['1572635196237-14b3f281503f', '1584036553516-bf83210aa16c', '1577803645773-f96470509666', '1511499767150-a48a237f0083', '1785672021160-29cccd65500d', '1764783103855-2b747b9c8417'],
    goggles: ['1607958674115-05b24858a945'],
    goalie: ['1632072820781-79f3a064f640'],
    boxing: ['1549719386-74dfcbf7dbed', '1669500635486-53b02ec7919b', '1521800641212-77d98bb90d21', '1779369958758-97376feb5aaa', '1780840156934-3ccb8dc05aa8'],
    soccerBall: ['1579952363873-27f3bade9f55', '1575361204480-aadea25e6e68', '1516567727245-ad8c68f3ec93', '1764040561192-dc860c4ecf8a', '1769356636430-e04718f78ee8', '1660926655155-8b1f8f9079f5'],
    basketball: ['1608508136365-89375aa78e0c', '1595861021888-e8192a7f774e', '1484482340112-e1e2682b4856', '1567113379515-6e85e7168eb1', '1627627256672-027a4613d028', '1528310782510-2f00164b609f'],
    volleyball: ['1666901356149-93f2eb3ba5a2', '1588492069485-d05b56b2831d', '1645398709708-eca899c71a11'],
    beachVolley: ['1592656094267-764a45160876', '1593115379577-a21ea97d6645'],
    americanFootball: ['1566577739254-faaafa7b4fa7', '1566579090262-51cde5ebe92e'],
    padelBalls: ['1658723826297-fe4d1b1e6600'],
    racket: ['1657704358775-ed705c7388d2', '1787768647981-dab3d52f8f04', '1612534847738-b3af9bc31f0c'],
    dumbbells: ['1638536532686-d610adfc8e5c', '1576678927484-cc907957088c', '1672344048213-76b6e77304bd', '1770493895453-4f758c40d11d', '1775993699105-4d18bcac7e54']
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
    ['guantes de box', 'boxing'], ['guantes de portero', 'goalie'],
    ['lentes de esqui', 'goggles'], ['goggles', 'goggles'], ['lentes', 'sunglasses'], ['mancuerna', 'dumbbells'], ['botella', 'bottle'],
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
    accessories: 'backpack', eyewear: 'sunglasses', gloves: 'boxing', balls: 'soccerBall'
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

/* Número estable a partir del nombre: el mismo producto siempre recibe la misma foto. */
function pick(list, name) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) hash = (hash * 31 + name.charCodeAt(i)) >>> 0;
    return list[hash % list.length];
}

export function stockPhoto(product) {
    const kind = photoKind(product);
    const list = kind ? PHOTOS[kind] : null;
    if (!list || !list.length) return null;
    return PHOTO_URL + pick(list, plain(product.name)) + PHOTO_SIZE;
}
