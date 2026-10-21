// UMKC VSA public site — English / Vietnamese.
//
// Only the Vietnamese lives here. The English is read back off the page the
// first time a language is applied and stashed on the element, so the HTML
// stays the single source of truth for it — there is no second copy of the
// English to drift out of sync, and switching back restores exactly what the
// page shipped with.
//
// Values may contain markup, because a highlighted phrase rarely lands in the
// same place in both languages: "Pull up. <span>You're invited.</span>" has to
// become "Ghé chơi nhé. <span>Bạn được mời rồi.</span>", not a swap of the
// span's contents. Every value below is a literal authored in this file — no
// user input ever reaches innerHTML through it.
//
// Not translated on purpose: Terms, Privacy and the Constitution (legal text
// where a loose translation is worse than none — those pages show a note in
// Vietnamese pointing at the English), and anything coming from the database
// — event names, announcements, sponsor names — which is stored in whatever
// language it was entered.
(function () {
  var VI = {
    // ---------- chrome, shared by every page ----------
    'nav.home': 'TRANG CHỦ',
    'nav.about': 'GIỚI THIỆU',
    'nav.aboutUs': 'Về chúng tôi',
    'nav.partners': 'Đối tác &amp; Nhà tài trợ',
    'nav.news': 'Tin tức',
    'nav.gallery': 'Thư viện ảnh',
    'nav.constitution': 'Điều lệ',
    'nav.eboard': 'BAN CHẤP HÀNH',
    'nav.merch': 'CỬA HÀNG',
    'nav.contact': 'LIÊN HỆ',
    'nav.login': 'Đăng nhập',
    'nav.portal': 'Cổng thành viên',

    'foot.blurb': 'Hết lòng quảng bá nghệ thuật và văn hóa Việt Nam, kết nối sinh viên và cộng đồng thông qua giáo dục, sự kiện và niềm trân trọng chung dành cho di sản phong phú của Việt Nam.',
    'foot.quick': 'Liên kết nhanh',
    'foot.about': 'Giới thiệu',
    'foot.eboard': 'Ban Chấp hành',
    'foot.merch': 'Cửa hàng',
    'foot.gallery': 'Thư viện ảnh',
    'foot.contact': 'Liên hệ',
    'foot.room': 'Tòa Student Union, Phòng 302',
    'foot.follow': 'Theo dõi chúng tôi',
    'foot.bar': '&copy; 2026 UMKC VSA. Bảo lưu mọi quyền. &middot; <a href="/terms/" style="color:var(--gold)">Điều khoản</a> &middot; <a href="/privacy/" style="color:var(--gold)">Bảo mật</a>',

    // ---------- home ----------
    'heroTitle': 'UMKC',
    'heroSubtitle': 'Hội Sinh viên Việt Nam',
    'heroText': 'Hãy trở thành một phần của cộng đồng sôi nổi của chúng tôi.',
    'joinButton': 'THAM GIA',
    'loginButton': 'Đăng nhập',

    'home.nextEvent': 'Sự kiện sắp tới',
    'home.cd.days': 'ngày',
    'home.cd.hrs': 'giờ',
    'home.cd.min': 'phút',
    'home.cd.sec': 'giây',

    'home.announcements': 'Thông báo',

    'home.who': 'Chúng tôi là ai',
    'home.whoH': 'Văn hóa, cộng đồng,<br>và một <span class="g">nơi thuộc về</span>.',
    'home.whoP': 'Hội Sinh viên Việt Nam là một tổ chức phi lợi nhuận độc lập, quảng bá nghệ thuật và văn hóa Việt Nam — kết nối những sinh viên và cộng đồng cùng trân trọng truyền thống phong phú và sức sáng tạo đương đại của Việt Nam.',
    'home.ourStory': 'Câu chuyện của chúng tôi',
    'home.photoSoon': 'Ảnh sẽ được cập nhật',

    'home.reveal': 'Ra mắt',

    'home.stat.members': 'Thành viên',
    'home.stat.events': 'Sự kiện đã tổ chức',
    'home.stat.photos': 'Ảnh đã chụp',
    'home.stat.est': 'Thành lập',

    'home.next': 'Sắp diễn ra',
    'home.nextH': 'Ghé chơi nhé. <span class="g">Bạn được mời rồi.</span>',

    'home.moments': 'Khoảnh khắc gần đây',
    'home.momentsH': 'Những đêm <span class="g">không thể quên</span>.',
    'home.viewGallery': 'Xem thư viện ảnh',

    'home.join': 'Tham gia cùng chúng tôi',
    'home.joinH': 'Hai phút để <span class="g">thuộc về</span>.',
    'home.step1H': 'Đăng ký',
    'home.step1P': 'Tạo tài khoản thành viên miễn phí — không mất phí, chào đón tất cả mọi người.',
    'home.step2H': 'Có mặt',
    'home.step2P': 'Đến một buổi họp hoặc sự kiện. Đồ ăn miễn phí, bạn bè dễ mến, không chút áp lực.',
    'home.step3H': 'Lên cấp',
    'home.step3P': 'Tích VND khi bạn tham gia và đổi lấy phần thưởng cùng vật phẩm có thật.',
    'home.createAccount': 'Tạo tài khoản',

    'home.sponsors': 'Nhà tài trợ',
    'home.sponsorsH': 'Được tiếp sức bởi <span class="g">những người tin tưởng</span>.',

    'home.finaleH': 'Khoác lên <span class="g">văn hóa</span>.',
    'home.finaleP': 'Trang phục và phụ kiện VSA độc quyền — mặc lên niềm tự hào.',
    'home.browseMerch': 'Xem cửa hàng',

    // ---------- about ----------
    'about.h1': 'Về chúng tôi',
    'about.sub': 'Khám phá câu chuyện, sứ mệnh và những con người đằng sau Hội Sinh viên Việt Nam UMKC.',
    'about.c1H': 'Văn hóa',
    'about.c1P': 'Gìn giữ và lan tỏa truyền thống, ẩm thực, âm nhạc và di sản phong phú của Việt Nam.',
    'about.c2H': 'Cộng đồng',
    'about.c2P': 'Xây dựng một gia đình gắn bó, nơi mỗi sinh viên đều được nhìn nhận, trân trọng và thấy như ở nhà.',
    'about.c3H': 'Phát triển',
    'about.c3P': 'Tiếp sức cho thành viên qua hoạt động lãnh đạo, giáo dục và những trải nghiệm ý nghĩa.',
    'about.stat.est': 'Thành lập',
    'about.stat.members': 'Thành viên',
    'about.stat.events': 'Sự kiện mỗi năm',
    'about.ctaH': 'Muốn trở thành một phần của câu chuyện này?',
    'about.ctaP': 'Tham gia cộng đồng sôi nổi tôn vinh văn hóa Việt Nam tại UMKC.',
    'about.ctaBtn': 'Tham gia ngay',

    // ---------- news ----------
    'news.h1': 'Tin tức',
    'news.sub': 'Thông báo, cập nhật và những điều đang diễn ra trong cộng đồng VSA.',
    'news.empty': 'Hiện chưa có bài nào — theo dõi <a href="https://www.instagram.com/umkc_vsa" target="_blank" style="color:var(--gold-bright)">@umkc_vsa</a> để cập nhật mới nhất.',
    'news.igOverline': 'Từ Instagram',
    'news.igH': 'Trực tiếp từ <span class="g">@umkc_vsa</span>.',
    'news.igBtn': 'Theo dõi trên Instagram',

    // ---------- partners ----------
    'partners.h1': 'Đối tác &amp; Nhà tài trợ',
    'partners.sub': 'Những tổ chức và cá nhân giúp các hoạt động của chúng tôi thành hiện thực.',
    'partners.empty': 'Danh sách nhà tài trợ năm nay đang được hoàn thiện — mời bạn quay lại sau.',
    'partners.ctaH': 'Đồng hành cùng chúng tôi',
    'partners.ctaP': 'Ủng hộ văn hóa Việt Nam tại UMKC và tiếp cận một cộng đồng sinh viên sôi nổi, gắn kết.',
    'partners.ctaBtn': 'Trở thành nhà tài trợ',

    // ---------- gallery ----------
    'gallery.h1': 'Thư viện ảnh',
    'gallery.sub': 'Những khoảnh khắc từ các sự kiện, lễ hội và buổi gặp mặt của chúng tôi.',

    // ---------- e-board ----------
    'eboard.h1': 'Ban Chấp hành',
    'eboard.sub': 'Những sinh viên tâm huyết giữ cho cộng đồng của chúng tôi luôn vững mạnh quanh năm.',

    // ---------- contact ----------
    'contact.h1': 'Liên hệ với chúng tôi',
    'contact.sub': 'Bạn có câu hỏi, ý tưởng, hoặc muốn hợp tác? Chúng tôi rất mong được nghe từ bạn.',
    'contact.first': 'Tên',
    'contact.firstPh': 'Tên của bạn',
    'contact.last': 'Họ',
    'contact.lastPh': 'Họ của bạn',
    'contact.subject': 'Tiêu đề',
    'contact.subjectPh': 'Bạn muốn trao đổi về điều gì?',
    'contact.message': 'Nội dung',
    'contact.messagePh': 'Viết lời nhắn của bạn ở đây...',
    'contact.send': 'Soạn email',
    'contact.note': 'Thao tác này mở ứng dụng email của bạn với địa chỉ support@umkcvsa.org và nội dung đã sẵn sàng để gửi.',

    // ---------- merch ----------
    'merch.h1': 'Cửa hàng VSA',
    'merch.sub': 'Thể hiện niềm tự hào VSA với trang phục và phụ kiện độc quyền của chúng tôi.',

    // ---------- legal pages: chrome only ----------
    'legal.note': 'Trang này chỉ có bản tiếng Anh. Đây là văn bản pháp lý, nên bản tiếng Anh là bản chính thức.'
  };

  var KEY = 'vsa-lang';
  var DICTS = { vi: VI };

  function stash(el) {
    if (el.getAttribute('data-i18n-en') === null) {
      el.setAttribute('data-i18n-en', el.innerHTML);
    }
  }

  function apply(lang) {
    var d = DICTS[lang] || null;

    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      stash(el);
      var v = d ? d[el.getAttribute('data-i18n')] : undefined;
      el.innerHTML = v !== undefined ? v : el.getAttribute('data-i18n-en');
    });

    // placeholders can't be reached by innerHTML
    document.querySelectorAll('[data-i18n-ph]').forEach(function (el) {
      if (el.getAttribute('data-i18n-ph-en') === null) {
        el.setAttribute('data-i18n-ph-en', el.getAttribute('placeholder') || '');
      }
      var v = d ? d[el.getAttribute('data-i18n-ph')] : undefined;
      el.setAttribute('placeholder', v !== undefined ? v : el.getAttribute('data-i18n-ph-en'));
    });

    // screen readers and search engines both read this
    document.documentElement.setAttribute('lang', lang === 'vi' ? 'vi' : 'en');

    document.querySelectorAll('.lang [data-lang]').forEach(function (b) {
      b.classList.toggle('on', b.getAttribute('data-lang') === lang);
    });

    window.__vsaLang = lang;
    try { localStorage.setItem(KEY, lang); } catch (e) {}
    window.dispatchEvent(new CustomEvent('vsa:lang', { detail: lang }));
  }

  function current() {
    try { return localStorage.getItem(KEY) === 'vi' ? 'vi' : 'en'; } catch (e) { return 'en'; }
  }

  function init() {
    document.querySelectorAll('.lang [data-lang]').forEach(function (b) {
      b.addEventListener('click', function () { apply(b.getAttribute('data-lang')); });
    });
    apply(current());
  }

  window.VSA_I18N = { apply: apply, current: current, t: function (k) {
    var d = DICTS[window.__vsaLang]; return (d && d[k]) || null;
  } };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
