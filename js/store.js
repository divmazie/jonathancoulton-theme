/**
 * Created by DAM on 11/11/15.
 */

var shopify_domain = "http://jonathan-coulton.myshopify.com";
var default_audio_format = "";

function CartItem(product_id,product_name,price,variants,variant,quantity,allow_multiple) {
    var self = this;
    self.product_id = product_id;
    self.product_name = product_name;
    self.price = price;
    self.quantity = ko.observable(quantity);
    self.calculated_cost = ko.computed(function () {
        return self.price * self.quantity();
    });
    self.variants = ko.observableArray(variants);
    self.variant = ko.observable(variant);
    self.variant_id = ko.computed(function() {
        return self.variant().id;
    });
    self.allow_multiple = allow_multiple;
    self.duplicate_item = function() {
        addToCart(self.product_id,self.product_name,self.price,self.variants(),self.variant_id(),self.allow_multiple);
    }
}
var ViewModel = function(start_cart) {
    var self = this;
    self.player_ready = ko.observable(false);
    self.current_song = ko.observable(0);
    self.current_song_title = ko.observable("Listen now");
    self.playing = ko.observable(false);
    self.store_view = ko.observable(store_section);
    self.remember_format = ko.observable(true);
    self.cart = ko.observableArray(start_cart);
    self.remove_product = function(product) {
        self.cart.remove(product);
    };
    self.pos_cart_number = ko.computed(function() {
        if (self.cart().length > 0) {
            return self.cart().length;
        } else {
            return "";
        }
    });
    self.total_cost = ko.computed(function() {
        var total = 0;
        for (var i=0; i<self.cart().length; i++) {
            total += self.cart()[i].calculated_cost();
        }
        return total;
    });
    self.checkout_link = ko.computed(function() {
        var link = shopify_domain + "/cart/";
        var comma = "";
        for (var i=0; i<self.cart().length; i++) {
            link += comma + self.cart()[i].variant_id() + ":" + self.cart()[i].quantity();
            comma = ",";
        }
        return link;
    });

    self.in_cart = function(product) {
        for (var i=0; i<self.cart().length; i++) {
            if (self.cart()[i].product_id == product.product_id) {
                return true;
            }
        }
        return false;
    };
    self.store_local = ko.computed(function() {
        if(typeof(Storage) !== "undefined") {
            sessionStorage.setItem('cart',ko.toJSON(self.cart()));
        }
    });
};

var start_cart = [];
if(typeof(Storage) !== "undefined" && sessionStorage.getItem('cart')) {
    //sessionStorage.setItem('cart',[]);
    //var stored_cart = start_cart;
    $('#debug').html(sessionStorage.getItem('cart'));
    var stored_cart = JSON.parse(sessionStorage.getItem('cart'));
    for (var i = 0; i < stored_cart.length; i++) {
        var variant = find_variant(stored_cart[i].variants,stored_cart[i].variant_id);
        start_cart.push(new CartItem(stored_cart[i].product_id,stored_cart[i].product_name,stored_cart[i].price,stored_cart[i].variants,variant,stored_cart[i].quantity,stored_cart[i].allow_multiple));
    }
}
var myViewModel = new ViewModel(start_cart);
ko.applyBindings(myViewModel);

window.onload = function() {
    window.history.replaceState({section: myViewModel.store_view()}, '', '');
};
window.onpopstate = function(event) {
    if (event.state && event.state.section) {
        myViewModel.store_view(event.state.section);
    }
};
function store_nav(section) {
    window.scrollTo(0,0);
    myViewModel.store_view(section);
    resize_tasks();
    window.history.pushState({section: section}, '', '/store/?store_section='+section);
}

function option_modal(product_id) {
    var product_modal = $('#product_modal_'+product_id);
    var default_button = product_modal.find('#product'+product_id+'_'+default_audio_format);
    if (default_button.size()) {
        default_button.click();
    } else if (product_modal.size()) {
        product_modal.modal('show');
    } else {
        window["add_"+product_id+"_to_cart"](product_id);
    }
}

function addToCart(product_id,product_name,price,variants,variant_id,allow_multiple) {
    var variant = find_variant(variants,variant_id);
    if (myViewModel.store_view() == 'downloads' && myViewModel.remember_format()) {
        default_audio_format = variant.option;
    }
    var product = new CartItem(product_id,product_name,price,variants,variant,1,allow_multiple);
    if (allow_multiple || !myViewModel.in_cart(product)) {
        myViewModel.cart.push(product);
    }
    $('#product_modal_'+product_id).modal('hide');
    $('#cart_confirm_product_name').html(product_name);
    $('#cart_confirm_modal').modal('show');
    setTimeout("$('#cart_confirm_modal').modal('hide')",1500);
}

function find_variant(variants,variant_id) {
    var variant = variants[0];
    for (var i = 0; i < variants.length; i++) {
        if (variants[i].id == variant_id) {
            variant = variants[i];
        }
    }
    return variant;
}