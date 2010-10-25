<?php namespace nmvc;

class Test1Model extends AppModel {
    public $binary_f = array('core\BinaryType');
    public $binary2_f = array('core\BinaryType', 3);
    public $boolean_f = array('core\BooleanType');
    public $bytes_f = array('core\BytesType');
    public $country_f = array('core\CountryType');
    public $date_f = array('core\DateType');
    public $enum_copy_f = array('core\EnumCopyType', 'Test1Model', "text_f");
    public $file_f = array('core\FileType');
    public $float_f = array('core\FloatType');
    public $integer_f = array('core\IntegerType');
    public $ip_address_changed_f = array('core\IpAddressChangedType');
    public $ip_address_created_f = array('core\IpAddressCreatedType');
    public $ip_address_f = array('core\IpAddressType');
    public $password_f = array('core\PasswordType');
    public $picture_id = array('core\PictureType');
    public $pointer_id = array('core\PointerType', 'Test1Model');
    public $select_model_id = array('core\SelectModelType', 'Test1Model');
    public $select_f = array('core\SelectType', array(0 => "foo", 1 => "bar", 5 => "baz"));
    public $serialized_f = array('core\SerializedType');
    public $text_area_f = array('core\TextAreaType');
    public $text_f = array('core\TextType');
    public $text2_f = array('core\TextType', 3);
    public $text3_f = array(VOLATILE_FIELD, 'core\TextType');
    public $timespan_f = array('core\TimespanType');
    public $timestamp_f = array('core\TimestampType');
    public $universial_reference_f = array('core\UniversialReferenceType');
    public $upload_id = array('core\UploadType');
    public $yes_no_f = array('core\YesNoType');
}