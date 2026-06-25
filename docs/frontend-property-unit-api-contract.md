# Frontend API Contract: Properties and Units

هذا الملف مخصص لفريق الفرونت إند لربط شاشات تعديل وحذف العقارات والوحدات.

Base URL:

```text
http://daryum-backend.city2tec.com/api
```

Previous backend Base URL:

```text
https://dev.daryum.com/api
```

في بيئة الإنتاج يتم استبدال `http://localhost/api` برابط الـ API الفعلي.

## Authentication

كل طلبات العقارات والوحدات محمية بـ Sanctum token.

### Login

```http
POST /api/login
Accept: application/json
Content-Type: application/json
```

Request body:

```json
{
  "email": "manager@example.com",
  "password": "password"
}
```

Expected success response:

```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح",
  "data": {
    "user": {
      "id": "uuid",
      "full_name": "User Name",
      "email": "manager@example.com",
      "role": "property_manager",
      "permissions": {
        "canManageProperties": true,
        "canViewProperties": true,
        "canManageUnits": true,
        "canViewUnits": true
      }
    },
    "token": "plain-text-sanctum-token"
  }
}
```

بعد تسجيل الدخول، يجب إرسال التوكن في كل الطلبات التالية:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

## Common Error Responses

### 401 Unauthorized

يحدث عند عدم إرسال التوكن أو عند انتهاء/خطأ التوكن.

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden

يحدث عندما لا يملك المستخدم الصلاحية المطلوبة.

صلاحيات العقارات:

```text
canViewProperties
canManageProperties
```

صلاحيات الوحدات:

```text
canViewUnits
canManageUnits
```

Example:

```json
{
  "message": "This action is unauthorized."
}
```

أو:

```json
{
  "success": false,
  "message": "غير مصرح لك بتنفيذ هذا الإجراء"
}
```

### 404 Not Found

يحدث عند إرسال `id` غير موجود أو سجل محذوف Soft Delete.

```json
{
  "message": "No query results for model..."
}
```

### 422 Validation Error

يحدث عند نقص أو خطأ في البيانات.

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Validation message"
    ]
  }
}
```

## Properties API

### Get Properties List

يستخدم لجلب العقارات واختيار `property.id` المطلوب للتعديل أو الحذف.

```http
GET /api/properties
Authorization: Bearer {token}
Accept: application/json
```

Optional query parameters:

| Parameter | Type | Description |
| --- | --- | --- |
| `search` | string | بحث بالاسم العربي/الإنجليزي أو المدينة |
| `status` | string | `active`, `inactive`, `maintenance` |
| `sort_by` | string | اسم الحقل للترتيب، الافتراضي `created_at` |
| `sort_direction` | string | `asc` أو `desc`، الافتراضي `desc` |
| `per_page` | integer | عدد العناصر في الصفحة، الافتراضي `15` |

Expected success response:

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "property-uuid",
        "user_id": "manager-user-uuid",
        "name": "Property Name",
        "name_ar": "اسم العقار",
        "description": null,
        "description_ar": null,
        "address": "Address",
        "address_ar": null,
        "city": "Riyadh",
        "city_ar": null,
        "state": null,
        "country": "Saudi Arabia",
        "postal_code": null,
        "latitude": null,
        "longitude": null,
        "property_type": "apartment",
        "total_units": 5,
        "cover_image_url": null,
        "amenities": [],
        "status": "active",
        "is_listed": true,
        "created_at": "2026-06-19T10:00:00.000000Z",
        "updated_at": "2026-06-19T10:00:00.000000Z",
        "user": {},
        "units": []
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

### BUG-001: Update Property

```http
PUT /api/properties/{propertyId}
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

يمكن أيضًا استخدام:

```http
PATCH /api/properties/{propertyId}
```

Path parameters:

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `propertyId` | uuid | yes | رقم العقار المراد تعديله |

Request body:

| Field | Type | Required | Allowed Values / Notes |
| --- | --- | --- | --- |
| `name` | string | no | max 255 |
| `name_ar` | string/null | no | max 255 |
| `description` | string/null | no |  |
| `description_ar` | string/null | no |  |
| `address` | string | no |  |
| `address_ar` | string/null | no |  |
| `city` | string | no |  |
| `city_ar` | string/null | no |  |
| `state` | string/null | no |  |
| `postal_code` | string/null | no |  |
| `latitude` | number/null | no |  |
| `longitude` | number/null | no |  |
| `property_type` | string | no | example: `apartment`, `villa`, `hotel`, `resort` |
| `total_units` | integer/null | no | minimum 0 |
| `cover_image_url` | string/null | no | valid URL |
| `amenities` | array/null | no |  |
| `status` | string | no | `active`, `inactive`, `maintenance` |
| `is_listed` | boolean/null | no |  |

Example request:

```json
{
  "name": "Updated Property",
  "address": "Updated address",
  "city": "Riyadh",
  "property_type": "villa",
  "status": "maintenance",
  "total_units": 8
}
```

Expected success response:

```json
{
  "success": true,
  "message": "تم تحديث العقار بنجاح",
  "data": {
    "id": "property-uuid",
    "user_id": "manager-user-uuid",
    "name": "Updated Property",
    "address": "Updated address",
    "city": "Riyadh",
    "property_type": "villa",
    "total_units": 8,
    "status": "maintenance",
    "is_listed": true,
    "created_at": "2026-06-19T10:00:00.000000Z",
    "updated_at": "2026-06-19T10:05:00.000000Z",
    "user": {},
    "units": []
  }
}
```

Frontend notes:

- يجب إرسال `propertyId` من القائمة أو من صفحة تفاصيل العقار.
- لو يستخدم الفورم `FormData` ولا يستطيع إرسال `PUT`، يمكن استخدام `POST` مع `_method=PUT` إذا كان الـ backend/frontend stack يدعم Laravel method override.
- لا ترسل قيم فارغة للحقول الرقمية إلا كـ `null` أو احذف الحقل.

### BUG-002: Delete Property

```http
DELETE /api/properties/{propertyId}
Authorization: Bearer {token}
Accept: application/json
```

Path parameters:

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `propertyId` | uuid | yes | رقم العقار المراد حذفه |

Expected success response:

```json
{
  "success": true,
  "message": "تم حذف العقار بنجاح"
}
```

Frontend notes:

- الحذف في الـ backend هو Soft Delete.
- بعد نجاح الحذف، احذف العقار من state أو أعد تحميل القائمة.
- إذا كان العقار يحتوي وحدات، العلاقة مضبوطة بـ cascade في قاعدة البيانات، لكن مع Soft Delete قد تظهر تفاصيل مرتبطة حسب طريقة الاستعلام. الأفضل للفرونت تحديث القائمة بعد الحذف.

## Units API

### Get Units List

يستخدم لجلب الوحدات واختيار `unit.id` المطلوب للتعديل أو الحذف.

```http
GET /api/units
Authorization: Bearer {token}
Accept: application/json
```

Optional query parameters:

| Parameter | Type | Description |
| --- | --- | --- |
| `property_id` | uuid | جلب وحدات عقار محدد |
| `status` | string | `available`, `occupied`, `cleaning`, `maintenance`, `blocked` |
| `per_page` | integer | عدد العناصر في الصفحة، الافتراضي `15` |

Expected success response:

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "unit-uuid",
        "property_id": "property-uuid",
        "owner_id": null,
        "unit_number": "A-101",
        "unit_name": "Suite 101",
        "unit_name_ar": null,
        "unit_type": "studio",
        "bedrooms": 1,
        "bathrooms": 1,
        "size_sqm": null,
        "max_guests": 2,
        "base_price": "250.00",
        "currency": "SAR",
        "status": "available",
        "floor_number": null,
        "amenities": [],
        "images": [],
        "cleaning_notes": null,
        "maintenance_notes": null,
        "created_at": "2026-06-19T10:00:00.000000Z",
        "updated_at": "2026-06-19T10:00:00.000000Z",
        "property": {},
        "owner": null
      }
    ],
    "per_page": 15,
    "total": 1
  }
}
```

### BUG-003: Update Unit

```http
PUT /api/units/{unitId}
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

يمكن أيضًا استخدام:

```http
PATCH /api/units/{unitId}
```

Path parameters:

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `unitId` | uuid | yes | رقم الوحدة المراد تعديلها |

Request body:

| Field | Type | Required | Allowed Values / Notes |
| --- | --- | --- | --- |
| `property_id` | uuid | no | must exist in `properties.id` |
| `owner_id` | uuid/null | no | must exist in `users.id` |
| `unit_number` | string | no | max 255 |
| `unit_name` | string/null | no | max 255 |
| `unit_name_ar` | string/null | no | max 255 |
| `unit_type` | string | no | example: `studio`, `1br`, `2br`, `villa` |
| `bedrooms` | integer/null | no | minimum 0 |
| `bathrooms` | integer/null | no | minimum 0 |
| `size_sqm` | number/null | no | minimum 0 |
| `max_guests` | integer/null | no | minimum 1 |
| `base_price` | number/null | no | minimum 0 |
| `currency` | string/null | no | exactly 3 chars, example `SAR` |
| `status` | string/null | no | `available`, `occupied`, `cleaning`, `maintenance`, `blocked` |
| `floor_number` | string/null | no | max 255 |
| `amenities` | array/null | no |  |
| `images` | array/null | no |  |
| `cleaning_notes` | string/null | no |  |
| `maintenance_notes` | string/null | no |  |

Example request:

```json
{
  "unit_number": "A-102",
  "unit_name": "Updated Suite",
  "unit_type": "1br",
  "status": "cleaning",
  "base_price": 325,
  "currency": "SAR"
}
```

Expected success response:

```json
{
  "success": true,
  "message": "Unit updated successfully",
  "data": {
    "id": "unit-uuid",
    "property_id": "property-uuid",
    "owner_id": null,
    "unit_number": "A-102",
    "unit_name": "Updated Suite",
    "unit_name_ar": null,
    "unit_type": "1br",
    "bedrooms": 1,
    "bathrooms": 1,
    "size_sqm": null,
    "max_guests": 2,
    "base_price": "325.00",
    "currency": "SAR",
    "status": "cleaning",
    "floor_number": null,
    "amenities": [],
    "images": [],
    "cleaning_notes": null,
    "maintenance_notes": null,
    "created_at": "2026-06-19T10:00:00.000000Z",
    "updated_at": "2026-06-19T10:05:00.000000Z",
    "property": {},
    "owner": null
  }
}
```

Frontend notes:

- لا يلزم إرسال `property_id` عند تعديل بيانات الوحدة العادية، إلا إذا كان المستخدم ينقل الوحدة إلى عقار آخر.
- إذا تم إرسال `property_id` يجب أن يكون المستخدم مصرحًا له بإدارة هذا العقار.
- يوجد unique constraint على `property_id + unit_number`، لذلك تكرار رقم الوحدة داخل نفس العقار قد يفشل من قاعدة البيانات.

### BUG-004: Delete Unit

```http
DELETE /api/units/{unitId}
Authorization: Bearer {token}
Accept: application/json
```

Path parameters:

| Parameter | Type | Required | Description |
| --- | --- | --- | --- |
| `unitId` | uuid | yes | رقم الوحدة المراد حذفها |

Expected success response:

```json
{
  "success": true,
  "message": "Unit deleted successfully"
}
```

Frontend notes:

- الحذف في الـ backend هو Soft Delete.
- بعد نجاح الحذف، احذف الوحدة من state أو أعد تحميل القائمة.

## Recommended Frontend Flow

### Edit Property Flow

1. جلب قائمة العقارات من `GET /api/properties`.
2. فتح modal/form للتعديل باستخدام بيانات العقار المحدد.
3. إرسال `PUT /api/properties/{propertyId}`.
4. عند `success=true`، تحديث العنصر في state أو إعادة تحميل القائمة.
5. عند `422`، عرض رسائل `errors` بجانب الحقول.

### Delete Property Flow

1. جلب `propertyId` من العنصر المختار.
2. عرض confirmation dialog.
3. إرسال `DELETE /api/properties/{propertyId}`.
4. عند النجاح، إزالة العنصر من القائمة أو إعادة تحميلها.

### Edit Unit Flow

1. جلب الوحدات من `GET /api/units` أو `GET /api/units?property_id={propertyId}`.
2. فتح modal/form للتعديل باستخدام بيانات الوحدة المحددة.
3. إرسال `PUT /api/units/{unitId}`.
4. عند `success=true`، تحديث العنصر في state أو إعادة تحميل القائمة.
5. عند `422`، عرض رسائل `errors` بجانب الحقول.

### Delete Unit Flow

1. جلب `unitId` من العنصر المختار.
2. عرض confirmation dialog.
3. إرسال `DELETE /api/units/{unitId}`.
4. عند النجاح، إزالة العنصر من القائمة أو إعادة تحميلها.

## Quick Checklist for Frontend Bugs

- تأكد أن الطلب يستخدم `PUT` أو `PATCH` للتعديل.
- تأكد أن الطلب يستخدم `DELETE` للحذف.
- تأكد من إرسال `Authorization: Bearer {token}`.
- تأكد من إرسال `Accept: application/json`.
- تأكد أن `propertyId` و `unitId` هما UUID الصحيح من الـ API وليس index من الجدول.
- تأكد أن المستخدم يملك صلاحية الإدارة:
  - `canManageProperties=true`
  - `canManageUnits=true`
- تأكد أن قيم `status` مطابقة للقيم المسموحة.
- تأكد أن الحقول الرقمية ترسل كأرقام أو `null`، وليس string فارغ.
